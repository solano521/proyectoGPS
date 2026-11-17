<?php
session_start();
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: proteccion_civil.php");
    exit();
}
if (!isset($_SESSION['id_usuario']) || ($_SESSION['id_area'] ?? 0) != 3) {
    $_SESSION['id_usuario'] = 6;
    $_SESSION['nombre'] = 'Protección Civil';
    $_SESSION['id_rol'] = 4;
    $_SESSION['rol'] = 'Protección Civil';
    $_SESSION['id_area'] = 3;
}

$host = "localhost";
$port = "3307";
$dbname = "Alerta_Campus";
$username = "root";
$password = "root123";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    
    $pdo->exec("INSERT IGNORE INTO categorias (id_categoria, id_area, nombre_categoria) VALUES
        (6, 3, 'Enjambre'),
        (7, 3, 'Fuga de Gas'),
        (8, 3, 'Fuga de Químicos'),
        (9, 3, 'Incendio'),
        (10, 3, 'Amenaza de Bomba')
    ");
} catch (PDOException $e) {
    die("Error de conexión BD: " . $e->getMessage());
}

$id_area = 3; 
$nombre_area = "Protección Civil";

$mensaje = "";
$tipo_alerta = "success";
$vista_activa = $_GET['seccion'] ?? 'activos'; 
$filtro_tipo = $_GET['tipo'] ?? 'todos';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'atender_reporte') {
        $id_rep = (int) $_POST['id_reporte'];
        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'En Proceso' WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$id_rep, $id_area]);

        $mensaje = "Emergencia marcada <strong>En Proceso</strong>. Unidad en camino / atendiendo.";
        $vista_activa = 'activos';
    }

    if ($accion === 'mas_tiempo') {
        $id_rep = (int) $_POST['id_reporte'];
        $nota_avance = trim($_POST['nota_avance']);
        $textoGuardar = "[En proceso - Requiere más tiempo]: " . ($nota_avance ?: "Personal de Protección Civil continúa asegurando y controlando la zona.");

        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'En Proceso', evidencia_cierre = ? WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$textoGuardar, $id_rep, $id_area]);

        $mensaje = "Actualización registrada: <strong>Requiere más tiempo de atención</strong>.";
        $tipo_alerta = "info";
        $vista_activa = 'activos';
    }

    if ($accion === 'finalizar_reporte') {
        $id_rep = (int) $_POST['id_reporte'];
        $solucion = trim($_POST['solucion_final']);
        $textoFinal = $solucion ?: "Emergencia controlada y zona asegurada por Protección Civil.";

        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'Resuelto', evidencia_cierre = ? WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$textoFinal, $id_rep, $id_area]);

        $mensaje = "¡Emergencia <strong>Finalizada y Controlada</strong>! Se retiró de las alertas activas.";
        $vista_activa = 'activos';
    }
}

$emergenciasPermitidas = ['Incendio', 'Enjambre', 'Amenaza de Bomba', 'Fuga de Gas', 'Fuga de Químicos'];

$sqlActivos = "
    SELECT r.*, c.nombre_categoria, u.edificio, u.detalle_lugar,
           usr.nombre_completo AS reportante, usr.identificador_institucional AS matricula
    FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    JOIN ubicaciones u ON r.id_ubicacion = u.id_ubicacion
    JOIN usuarios usr ON r.id_usuario_reporta = usr.id_usuario
    WHERE r.id_area_asignada = ?
      AND r.estatus IN ('Pendiente', 'En Proceso')
      AND c.nombre_categoria IN ('Incendio', 'Enjambre', 'Amenaza de Bomba', 'Fuga de Gas', 'Fuga de Químicos')
";
$params = [$id_area];

if ($filtro_tipo !== 'todos' && in_array($filtro_tipo, $emergenciasPermitidas, true)) {
    $sqlActivos .= " AND c.nombre_categoria = ?";
    $params[] = $filtro_tipo;
}
$sqlActivos .= " ORDER BY r.fecha_creacion ASC";

$stmtActivos = $pdo->prepare($sqlActivos);
$stmtActivos->execute($params);
$reportesActivos = $stmtActivos->fetchAll();

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    WHERE r.id_area_asignada = ?
      AND r.estatus IN ('Pendiente', 'En Proceso')
      AND c.nombre_categoria IN ('Incendio', 'Enjambre', 'Amenaza de Bomba', 'Fuga de Gas', 'Fuga de Químicos')
");
$stmtTotal->execute([$id_area]);
$totalPendientes = (int) $stmtTotal->fetchColumn();

$stmtResueltos = $pdo->prepare("
    SELECT r.*, c.nombre_categoria, u.edificio, u.detalle_lugar
    FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    JOIN ubicaciones u ON r.id_ubicacion = u.id_ubicacion
    WHERE r.id_area_asignada = ? AND r.estatus IN ('Resuelto', 'Cerrado')
    ORDER BY r.fecha_creacion DESC
    LIMIT 20
");
$stmtResueltos->execute([$id_area]);
$reportesFinalizados = $stmtResueltos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlertaCampus ITZ | Protección Civil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; overflow-x: hidden; }
        .navbar-pc { background: linear-gradient(135deg, #7f1d1d 0%, #b91c1c 100%); }
        .logo-navbar { width: 36px; height: 36px; background: #fff; border-radius: 50%; padding: 3px; object-fit: contain; }
        .contenedor-principal { max-width: 720px; margin: 0 auto; }
        .card-app { border: none; border-radius: 16px; box-shadow: 0 4px 16px rgba(127, 29, 29, 0.10); }
        .form-control { border-radius: 10px; padding: 11px 12px; border: 1px solid #ced4da; }
        .btn-pc { background-color: #b91c1c; border: none; border-radius: 12px; padding: 14px; font-weight: 600; color: #fff; }
        .btn-pc:hover { background-color: #7f1d1d; color: #fff; }
        .accordion-item { border: 1px solid #e9ecef; border-left: 5px solid #dc3545; border-radius: 12px !important; margin-bottom: 12px; overflow: hidden; }
        .accordion-button:not(.collapsed) { background-color: #fef2f2; color: #7f1d1d; }
        .caja-accion { background-color: #f8f9fa; border: 1px solid #e2e6ea; border-radius: 12px; padding: 14px; }
        .filtro-pill { font-size: 0.78rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-pc py-3 mb-4 shadow-sm">
    <div class="container contenedor-principal d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2 mb-0" href="?seccion=activos">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQOusK5rsOeBHWjKgx-jqWZw6QYdq3FgRVNC2nJqVIlRAxdC6ZgL_b22s4&s=10" alt="Logo" class="logo-navbar">
            <div>
                <span class="d-block lh-1">AlertaCampus <small class="fw-normal opacity-75">ITZ</small></span>
                <small class="text-warning fw-semibold" style="font-size: 0.74rem;"><i class="bi bi-shield-exclamation me-1"></i><?= htmlspecialchars($nombre_area) ?></small>
            </div>
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="?seccion=activos" class="btn btn-sm <?= $vista_activa === 'activos' ? 'btn-warning text-dark fw-bold' : 'btn-outline-light' ?> rounded-pill px-3 py-2 d-flex align-items-center gap-1">
                <i class="bi bi-bell-fill"></i>
                <span class="d-none d-sm-inline">Alertas</span>
                <?php if ($totalPendientes > 0): ?>
                    <span class="badge <?= $vista_activa === 'activos' ? 'bg-danger text-white' : 'bg-warning text-dark' ?> rounded-pill ms-1"><?= $totalPendientes ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-1 rounded-pill px-3 py-2" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-list fs-5"></i>
                    <span>Menú</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2" style="min-width: 230px;">
                    <li class="px-3 py-2 border-bottom">
                        <small class="text-muted d-block">Corporación / Área</small>
                        <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($nombre_area) ?></span>
                    </li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'activos' ? 'active' : '' ?>" href="?seccion=activos"><i class="bi bi-exclamation-octagon me-2"></i>Emergencias activas (<?= $totalPendientes ?>)</a></li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'historial' ? 'active' : '' ?>" href="?seccion=historial"><i class="bi bi-shield-check me-2"></i>Atendidas / Controladas (<?= count($reportesFinalizados) ?>)</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 text-danger fw-semibold" href="?logout=1"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container contenedor-principal pb-5">

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($vista_activa === 'activos'): ?>
        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Alertas de Emergencia Activas</h5>
                    <small class="text-muted">Atención inmediata en el campus ITZ</small>
                </div>
                <a href="?seccion=historial" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    Controladas (<?= count($reportesFinalizados) ?>)
                </a>
            </div>

            <!-- Filtros rápidos por tipo de emergencia -->
            <div class="d-flex flex-wrap gap-1 mb-4">
                <a href="?seccion=activos&tipo=todos" class="btn btn-sm filtro-pill rounded-pill <?= $filtro_tipo === 'todos' ? 'btn-danger' : 'btn-outline-secondary' ?>">Todas</a>
                <?php foreach ($emergenciasPermitidas as $tipo): ?>
                    <a href="?seccion=activos&tipo=<?= urlencode($tipo) ?>" class="btn btn-sm filtro-pill rounded-pill <?= $filtro_tipo === $tipo ? 'btn-danger' : 'btn-outline-secondary' ?>">
                        <?= htmlspecialchars($tipo) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($reportesActivos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shield-check fs-1 d-block mb-2 text-success opacity-75"></i>
                    <p class="fw-semibold mb-1">Sin emergencias activas en este momento</p>
                    <small>El campus se encuentra sin alertas pendientes para Protección Civil.</small>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionEmergencias">
                    <?php foreach ($reportesActivos as $index => $r): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?> py-3" type="button" data-bs-toggle="collapse" data-bs-target="#rep<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-danger d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= htmlspecialchars($r['edificio']) ?> • <?= date('H:i', strtotime($r['fecha_creacion'])) ?> hrs</small>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 flex-wrap justify-content-end">
                                            <?php if ($r['estatus'] === 'En Proceso'): ?>
                                                <span class="badge bg-info text-dark rounded-pill px-2 py-1"><i class="bi bi-shield-shaded me-1"></i>En atención</span>
                                            <?php endif; ?>
                                            <span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Inmediata</span>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="rep<?= $r['id_reporte'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#accordionEmergencias">
                                <div class="accordion-body bg-white">
                                    <div class="row g-2 mb-3 small">
                                        <div class="col-sm-6">
                                            <strong><i class="bi bi-geo-alt-fill text-danger me-1"></i>Ubicación en Campus:</strong><br>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($r['edificio'] . (!empty($r['detalle_lugar']) ? ' — ' . $r['detalle_lugar'] : '')) ?></span>
                                        </div>
                                        <div class="col-sm-6">
                                            <strong><i class="bi bi-person text-primary me-1"></i>Reportado por:</strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($r['reportante']) ?> (<?= htmlspecialchars($r['matricula']) ?>)</span>
                                        </div>
                                    </div>

                                    <p class="mb-3 small">
                                        <strong><i class="bi bi-chat-left-text text-secondary me-1"></i>Detalles de la emergencia:</strong><br>
                                        <span class="text-dark"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></span>
                                    </p>

                                    <?php if (!empty($r['evidencia_cierre'])): ?>
                                        <div class="alert alert-info py-2 px-3 small mb-3">
                                            <i class="bi bi-clock-history me-1"></i><strong>Bitácora:</strong> <?= htmlspecialchars($r['evidencia_cierre']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($r['estatus'] === 'Pendiente'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="atender_reporte">
                                            <input type="hidden" name="id_reporte" value="<?= $r['id_reporte'] ?>">
                                            <button type="submit" class="btn btn-pc w-100">
                                                <i class="bi bi-shield-fill-exclamation me-2"></i>Atender Emergencia
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="caja-accion">
                                            <label class="form-label small fw-bold text-secondary mb-1">Reporte de acción / control de zona (opcional):</label>
                                            <form method="POST">
                                                <input type="hidden" name="id_reporte" value="<?= $r['id_reporte'] ?>">
                                                <input type="text" name="solucion_final" id="nota_<?= $r['id_reporte'] ?>" class="form-control form-control-sm mb-3" placeholder="Ej. Se retiró enjambre / Se cerró válvula y ventiló el área...">
                                                <div class="row g-2">
                                                    <div class="col-sm-6">
                                                        <button type="submit" name="accion" value="mas_tiempo" onclick="document.getElementById('avance_<?= $r['id_reporte'] ?>').value = document.getElementById('nota_<?= $r['id_reporte'] ?>').value;" class="btn btn-outline-warning text-dark fw-semibold btn-sm w-100 py-2">
                                                            <i class="bi bi-hourglass-split me-1"></i>Necesita más tiempo
                                                        </button>
                                                        <input type="hidden" name="nota_avance" id="avance_<?= $r['id_reporte'] ?>" value="">
                                                    </div>
                                                    <div class="col-sm-6">
                                                        <button type="submit" name="accion" value="finalizar_reporte" class="btn btn-success fw-semibold btn-sm w-100 py-2">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Finalizado
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- HISTORIAL DE EMERGENCIAS CONTROLADAS -->
        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Emergencias Controladas</h5>
                    <small class="text-muted">Historial de incidentes atendidos por Protección Civil</small>
                </div>
                <a href="?seccion=activos" class="btn btn-outline-danger btn-sm rounded-pill px-3">Activas (<?= $totalPendientes ?>)</a>
            </div>

            <?php if (empty($reportesFinalizados)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-archive fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">Aún no hay emergencias registradas en el historial.</p>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionHistorial">
                    <?php foreach ($reportesFinalizados as $r): ?>
                        <div class="accordion-item" style="border-left-color: #198754;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#fin<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= htmlspecialchars($r['edificio']) ?></small>
                                        </div>
                                        <span class="badge bg-success rounded-pill px-3 py-2">Controlado</span>
                                    </div>
                                </button>
                            </h2>
                            <div id="fin<?= $r['id_reporte'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionHistorial">
                                <div class="accordion-body bg-white small">
                                    <p class="mb-2"><strong>Lugar:</strong> <?= htmlspecialchars($r['edificio']) ?></p>
                                    <p class="mb-2"><strong>Detalles:</strong> <?= nl2br(htmlspecialchars($r['descripcion'])) ?></p>
                                    <div class="alert alert-success py-2 px-3 mb-0">
                                        <strong><i class="bi bi-shield-check me-1"></i>Resolución:</strong><br>
                                        <?= htmlspecialchars($r['evidencia_cierre'] ?: 'Emergencia controlada sin observaciones adicionales.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>