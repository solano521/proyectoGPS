<?php
session_start();
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: mantenimiento.php");
    exit();
}

if (!isset($_SESSION['id_usuario']) || ($_SESSION['id_area'] ?? 0) != 2) {
    $_SESSION['id_usuario'] = 5;
    $_SESSION['nombre'] = 'Depto. Mantenimiento';
    $_SESSION['id_rol'] = 4;
    $_SESSION['rol'] = 'Mantenimiento e Infraestructura';
    $_SESSION['id_area'] = 2;
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


    $pdo->exec("INSERT IGNORE INTO usuarios (id_usuario, nombre_completo, identificador_institucional, correo, password_hash, id_rol) VALUES 
        (5, 'Depto. Mantenimiento', 'DEP-MANT-01', 'mantenimiento@zacatepec.tecnm.mx', '123456', 4)
    ");

    $existeRecMat = $pdo->query("SELECT COUNT(*) FROM ubicaciones WHERE edificio = 'Recursos Materiales'")->fetchColumn();
    if ($existeRecMat == 0) {
        $pdo->exec("INSERT INTO ubicaciones (edificio, detalle_lugar) VALUES ('Recursos Materiales', '')");
    }
} catch (PDOException $e) {
    die("Error de conexión BD: " . $e->getMessage());
}

function calcularPrioridadAutomatica(PDO $pdo, int $id_categoria, int $id_ubicacion, string $nombre_categoria, string $subcategoria, string $descripcion): string {
    $texto = mb_strtolower($nombre_categoria . ' ' . $subcategoria . ' ' . $descripcion, 'UTF-8');

    $palabrasCriticas = [
        'enjambre', 'abeja', 'avispa', 'alacran', 'alacrán', 'serpiente',
        'fuga de gas', 'fuga de químicos', 'fuga de quimicos', 'químico', 'quimico', 'gas',
        'fuga de agua', 'mucha agua', 'inund', 'desbord',
        'corto', 'chispa', 'humo', 'fuego', 'incendio', 'cable expuesto', 'cable pelado',
        'accidente', 'herido', 'sangre', 'vidrio roto'
    ];
    foreach ($palabrasCriticas as $palabra) {
        if (str_contains($texto, $palabra)) {
            return 'Critica';
        }
    }

    $palabrasAltas = [
        'tapado', 'apagón', 'apagon', 'sin luz', 'sin suministro', 'sin agua', 'cerradura', 'aire acondicionado', 'clima'
    ];
    $prioridadBase = 'Media';
    foreach ($palabrasAltas as $palabra) {
        if (str_contains($texto, $palabra)) {
            $prioridadBase = 'Alta';
            break;
        }
    }

    $stmtConteo = $pdo->prepare("
        SELECT COUNT(*) FROM reportes 
        WHERE id_categoria = ? AND id_ubicacion = ? 
          AND estatus IN ('Pendiente', 'En Proceso')
          AND fecha_creacion >= NOW() - INTERVAL 24 HOUR
    ");
    $stmtConteo->execute([$id_categoria, $id_ubicacion]);
    $reportesSimilares = (int) $stmtConteo->fetchColumn();

    if ($reportesSimilares >= 2) {
        $pdo->prepare("UPDATE reportes SET prioridad = 'Critica' WHERE id_categoria = ? AND id_ubicacion = ? AND estatus IN ('Pendiente', 'En Proceso')")
            ->execute([$id_categoria, $id_ubicacion]);
        return 'Critica';
    }

    if ($reportesSimilares === 1 && $prioridadBase === 'Media') {
        $pdo->prepare("UPDATE reportes SET prioridad = 'Alta' WHERE id_categoria = ? AND id_ubicacion = ? AND estatus IN ('Pendiente', 'En Proceso')")
            ->execute([$id_categoria, $id_ubicacion]);
        return 'Alta';
    }

    return $prioridadBase;
}

$id_usuario = $_SESSION['id_usuario'];
$id_area = 2; 
$nombre_area = "Mantenimiento e Infraestructura";

$mensaje = "";
$tipo_alerta = "success";
$vista_activa = $_GET['seccion'] ?? 'activos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';


    if ($accion === 'atender_reporte') {
        $id_rep = (int) $_POST['id_reporte'];
        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'En Proceso' WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$id_rep, $id_area]);

        $mensaje = "Reporte marcado <strong>En Proceso</strong>. El usuario ya fue notificado.";
        $vista_activa = 'activos';
    }

    if ($accion === 'mas_tiempo') {
        $id_rep = (int) $_POST['id_reporte'];
        $nota_avance = trim($_POST['nota_avance']);
        $textoGuardar = "[En proceso - Requiere más tiempo]: " . ($nota_avance ?: "El personal de mantenimiento continúa trabajando en la reparación.");

        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'En Proceso', evidencia_cierre = ? WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$textoGuardar, $id_rep, $id_area]);

        $mensaje = "Actualización guardada: <strong>Necesita más tiempo</strong>.";
        $tipo_alerta = "info";
        $vista_activa = 'activos';
    }

    if ($accion === 'finalizar_reporte') {
        $id_rep = (int) $_POST['id_reporte'];
        $solucion = trim($_POST['solucion_final']);
        $textoFinal = $solucion ?: "Falla de mantenimiento/sanitarios reparada y finalizada.";

        $stmt = $pdo->prepare("UPDATE reportes SET estatus = 'Resuelto', evidencia_cierre = ? WHERE id_reporte = ? AND id_area_asignada = ?");
        $stmt->execute([$textoFinal, $id_rep, $id_area]);

        $mensaje = "¡Reporte <strong>Finalizado</strong>! Se ha retirado de tus pendientes.";
        $vista_activa = 'activos';
    }

    if ($accion === 'crear_reporte') {
        $id_cat = (int) $_POST['id_categoria'];
        $subcat = trim($_POST['subcategoria'] ?? '');
        $otroDetalle = trim($_POST['otro_detalle'] ?? '');
        $id_ubi = (int) $_POST['id_ubicacion'];
        $desc = trim($_POST['descripcion']);

        if ($subcat === 'Otro' && $otroDetalle !== '') {
            $subcat = "Otro: $otroDetalle";
        }

        $stmtCat = $pdo->prepare("SELECT c.id_area, c.nombre_categoria, a.nombre_area FROM categorias c JOIN areas_atencion a ON c.id_area = a.id_area WHERE c.id_categoria = ?");
        $stmtCat->execute([$id_cat]);
        $catData = $stmtCat->fetch();

        $areaAsignada = $catData['id_area'] ?? 1;
        $nombreAreaDestino = $catData['nombre_area'] ?? 'Departamento correspondiente';
        $nombreCategoria = $catData['nombre_categoria'] ?? 'Incidencia reportada';
        $tituloAuto = $subcat !== '' ? "$nombreCategoria ($subcat)" : $nombreCategoria;
        $descFinal = $subcat !== '' ? "Detalle específico: $subcat.\n$desc" : $desc;

        $prioAuto = calcularPrioridadAutomatica($pdo, $id_cat, $id_ubi, $nombreCategoria, $subcat, $desc);

        $folio = "ITZ-" . date("Y") . "-" . str_pad(rand(100, 9999), 4, "0", STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO reportes (folio, id_usuario_reporta, id_categoria, id_ubicacion, id_area_asignada, titulo, descripcion, prioridad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$folio, $id_usuario, $id_cat, $id_ubi, $areaAsignada, $tituloAuto, $descFinal, $prioAuto]);

        $mensaje = "¡Alerta enviada a <strong>$nombreAreaDestino</strong>! Folio de seguimiento: <strong>$folio</strong>.";
        $vista_activa = ($areaAsignada == $id_area) ? 'activos' : 'mis_enviados';
    }
}

$stmtActivos = $pdo->prepare("
    SELECT r.*, c.nombre_categoria, u.edificio, u.detalle_lugar,
           usr.nombre_completo AS reportante, usr.identificador_institucional AS matricula
    FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    JOIN ubicaciones u ON r.id_ubicacion = u.id_ubicacion
    JOIN usuarios usr ON r.id_usuario_reporta = usr.id_usuario
    WHERE r.id_area_asignada = ? AND r.estatus IN ('Pendiente', 'En Proceso')
    ORDER BY FIELD(r.prioridad, 'Critica', 'Alta', 'Media', 'Baja'), r.fecha_creacion ASC
");
$stmtActivos->execute([$id_area]);
$reportesActivos = $stmtActivos->fetchAll();

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

$stmtMisEnviados = $pdo->prepare("
    SELECT r.*, c.nombre_categoria, u.edificio, u.detalle_lugar, a.nombre_area
    FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    JOIN ubicaciones u ON r.id_ubicacion = u.id_ubicacion
    JOIN areas_atencion a ON r.id_area_asignada = a.id_area
    WHERE r.id_usuario_reporta = ?
    ORDER BY r.fecha_creacion DESC
");
$stmtMisEnviados->execute([$id_usuario]);
$misEnviados = $stmtMisEnviados->fetchAll();


$categorias = $pdo->query("SELECT * FROM categorias ORDER BY id_categoria ASC")->fetchAll();
$ubicaciones = $pdo->query("SELECT * FROM ubicaciones ORDER BY id_ubicacion ASC")->fetchAll();

function badgePrioridad(string $prioridad): string {
    return match($prioridad) {
        'Critica' => '<span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Crítica</span>',
        'Alta'    => '<span class="badge bg-warning text-dark rounded-pill px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Alta</span>',
        'Media'   => '<span class="badge bg-primary rounded-pill px-2 py-1">Media</span>',
        default   => '<span class="badge bg-secondary rounded-pill px-2 py-1">Baja</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlertaCampus ITZ | Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; overflow-x: hidden; }
        .navbar-itz { background: linear-gradient(135deg, #0b2545 0%, #134074 100%); }
        .logo-navbar { width: 36px; height: 36px; background: #fff; border-radius: 50%; padding: 3px; object-fit: contain; }
        .contenedor-principal { max-width: 720px; margin: 0 auto; }
        .card-app { border: none; border-radius: 16px; box-shadow: 0 4px 16px rgba(11, 37, 69, 0.08); }
        .form-select, .form-control { border-radius: 10px; padding: 12px; border: 1px solid #ced4da; }
        .form-select:focus, .form-control:focus { border-color: #134074; box-shadow: 0 0 0 0.2rem rgba(19, 64, 116, 0.15); }
        .caja-subcategoria { background-color: #f4f8fd; border-left: 4px solid #134074; border-radius: 10px; padding: 12px 14px; animation: aparecerSuave 0.25s ease-out; }
        @keyframes aparecerSuave { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
        .btn-itz { background-color: #134074; border: none; border-radius: 12px; padding: 14px; font-weight: 600; color: #fff; }
        .btn-itz:hover { background-color: #0b2545; color: #fff; }
        .accordion-item { border: 1px solid #e9ecef; border-radius: 12px !important; margin-bottom: 12px; overflow: hidden; }
        .accordion-item.borde-critico { border-left: 5px solid #dc3545; }
        .accordion-item.borde-alto { border-left: 5px solid #ffc107; }
        .accordion-button:not(.collapsed) { background-color: #eef4fc; color: #0b2545; }
        .caja-accion { background-color: #f8f9fa; border: 1px solid #e2e6ea; border-radius: 12px; padding: 14px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-itz py-3 mb-4 shadow-sm">
    <div class="container contenedor-principal d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2 mb-0" href="?seccion=activos">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQOusK5rsOeBHWjKgx-jqWZw6QYdq3FgRVNC2nJqVIlRAxdC6ZgL_b22s4&s=10" alt="Logo" class="logo-navbar">
            <div>
                <span class="d-block lh-1">AlertaCampus <small class="fw-normal opacity-75">ITZ</small></span>
                <small class="text-warning fw-normal" style="font-size: 0.72rem;"><?= htmlspecialchars($nombre_area) ?></small>
            </div>
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="?seccion=activos" class="btn btn-sm <?= $vista_activa === 'activos' ? 'btn-warning text-dark fw-bold' : 'btn-outline-light' ?> rounded-pill px-3 py-2 d-flex align-items-center gap-1">
                <i class="bi bi-wrench-adjustable"></i>
                <span class="d-none d-sm-inline">Pendientes</span>
                <?php if (count($reportesActivos) > 0): ?>
                    <span class="badge <?= $vista_activa === 'activos' ? 'bg-dark text-white' : 'bg-warning text-dark' ?> rounded-pill ms-1"><?= count($reportesActivos) ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-1 rounded-pill px-3 py-2" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-list fs-5"></i>
                    <span>Opciones</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2" style="min-width: 245px;">
                    <li class="px-3 py-2 border-bottom">
                        <small class="text-muted d-block">Departamento</small>
                        <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($nombre_area) ?></span>
                    </li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'activos' ? 'active' : '' ?>" href="?seccion=activos"><i class="bi bi-inbox me-2"></i>Por atender (<?= count($reportesActivos) ?>)</a></li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'historial' ? 'active' : '' ?>" href="?seccion=historial"><i class="bi bi-check2-circle me-2"></i>Finalizados (<?= count($reportesFinalizados) ?>)</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'nuevo' ? 'active' : '' ?>" href="?seccion=nuevo"><i class="bi bi-plus-circle me-2"></i>Levantar reporte</a></li>
                    <li><a class="dropdown-item py-2 <?= $vista_activa === 'mis_enviados' ? 'active' : '' ?>" href="?seccion=mis_enviados"><i class="bi bi-send-check me-2"></i>Reportes que envié (<?= count($misEnviados) ?>)</a></li>
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
                    <h5 class="fw-bold text-dark mb-0">Sanitarios, Agua y Electricidad</h5>
                    <small class="text-muted">Ordenados automáticamente por urgencia</small>
                </div>
                <a href="?seccion=nuevo" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i>Reportar falla
                </a>
            </div>

            <?php if (empty($reportesActivos)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-75"></i>
                    <p class="fw-semibold mb-1">¡Sin reportes pendientes en Mantenimiento!</p>
                    <small>Todos los reportes de tu área han sido atendidos.</small>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionActivos">
                    <?php foreach ($reportesActivos as $index => $r): ?>
                        <?php $claseBorde = match($r['prioridad']) { 'Critica' => 'borde-critico', 'Alta' => 'borde-alto', default => '' }; ?>
                        <div class="accordion-item <?= $claseBorde ?>">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?> py-3" type="button" data-bs-toggle="collapse" data-bs-target="#rep<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-primary d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= htmlspecialchars($r['edificio']) ?></small>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 flex-wrap justify-content-end">
                                            <?php if ($r['estatus'] === 'En Proceso'): ?>
                                                <span class="badge bg-info text-dark rounded-pill px-2 py-1"><i class="bi bi-gear-fill me-1"></i>En proceso</span>
                                            <?php endif; ?>
                                            <?= badgePrioridad($r['prioridad']) ?>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="rep<?= $r['id_reporte'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#accordionActivos">
                                <div class="accordion-body bg-white">
                                    <div class="row g-2 mb-3 small">
                                        <div class="col-sm-6">
                                            <strong><i class="bi bi-geo-alt text-danger me-1"></i>Ubicación:</strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($r['edificio'] . (!empty($r['detalle_lugar']) ? ' — ' . $r['detalle_lugar'] : '')) ?></span>
                                        </div>
                                        <div class="col-sm-6">
                                            <strong><i class="bi bi-person text-primary me-1"></i>Reportado por:</strong><br>
                                            <span class="text-muted"><?= htmlspecialchars($r['reportante']) ?> (<?= htmlspecialchars($r['matricula']) ?>)</span>
                                        </div>
                                    </div>

                                    <p class="mb-3 small">
                                        <strong><i class="bi bi-chat-left-text text-secondary me-1"></i>Descripción del problema:</strong><br>
                                        <span class="text-dark"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></span>
                                    </p>

                                    <?php if (!empty($r['evidencia_cierre'])): ?>
                                        <div class="alert alert-info py-2 px-3 small mb-3">
                                            <i class="bi bi-clock-history me-1"></i><strong>Nota registrada:</strong> <?= htmlspecialchars($r['evidencia_cierre']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($r['estatus'] === 'Pendiente'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="atender_reporte">
                                            <input type="hidden" name="id_reporte" value="<?= $r['id_reporte'] ?>">
                                            <button type="submit" class="btn btn-itz w-100">
                                                <i class="bi bi-hand-index-thumb-fill me-2"></i>Atender
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="caja-accion">
                                            <label class="form-label small fw-bold text-secondary mb-1">Observación o solución (opcional):</label>
                                            <form method="POST">
                                                <input type="hidden" name="id_reporte" value="<?= $r['id_reporte'] ?>">
                                                <input type="text" name="solucion_final" id="nota_<?= $r['id_reporte'] ?>" class="form-control form-control-sm mb-3" placeholder="Ej. Se destapó tubería / Se cambió balastra...">
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

    <?php elseif ($vista_activa === 'historial'): ?>
        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Reportes Finalizados</h5>
                    <small class="text-muted">Incidencias resueltas por Mantenimiento</small>
                </div>
                <a href="?seccion=activos" class="btn btn-outline-primary btn-sm rounded-pill px-3">Pendientes (<?= count($reportesActivos) ?>)</a>
            </div>

            <?php if (empty($reportesFinalizados)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-archive fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">Aún no hay reportes finalizados.</p>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionHistorial">
                    <?php foreach ($reportesFinalizados as $r): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#fin<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= htmlspecialchars($r['edificio']) ?></small>
                                        </div>
                                        <span class="badge bg-success rounded-pill px-3 py-2">Finalizado</span>
                                    </div>
                                </button>
                            </h2>
                            <div id="fin<?= $r['id_reporte'] ?>" class="accordion-collapse collapse" data-bs-parent="#accordionHistorial">
                                <div class="accordion-body bg-white small">
                                    <p class="mb-2"><strong>Lugar:</strong> <?= htmlspecialchars($r['edificio']) ?></p>
                                    <p class="mb-2"><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($r['descripcion'])) ?></p>
                                    <div class="alert alert-success py-2 px-3 mb-0">
                                        <strong><i class="bi bi-check2-all me-1"></i>Solución:</strong><br>
                                        <?= htmlspecialchars($r['evidencia_cierre'] ?: 'Finalizado sin observaciones.') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($vista_activa === 'mis_enviados'): ?>

        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Reportes que Envié</h5>
                    <small class="text-muted">Seguimiento de incidencias reportadas por Mantenimiento</small>
                </div>
                <a href="?seccion=nuevo" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo
                </a>
            </div>

            <?php if (empty($misEnviados)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-3">Aún no has levantado reportes hacia otras áreas.</p>
                    <a href="?seccion=nuevo" class="btn btn-itz btn-sm px-4">Levantar reporte</a>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionEnviados">
                    <?php foreach ($misEnviados as $index => $r): ?>
                        <?php
                        $badgeColor = match($r['estatus']) {
                            'Pendiente' => 'bg-warning text-dark',
                            'En Proceso' => 'bg-info text-dark',
                            'Resuelto' => 'bg-success',
                            default => 'bg-secondary'
                        };
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?> py-3" type="button" data-bs-toggle="collapse" data-bs-target="#env<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-primary d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= date('d/m/Y H:i', strtotime($r['fecha_creacion'])) ?></small>
                                        </div>
                                        <span class="badge <?= $badgeColor ?> rounded-pill px-3 py-2"><?= $r['estatus'] ?></span>
                                    </div>
                                </button>
                            </h2>
                            <div id="env<?= $r['id_reporte'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#accordionEnviados">
                                <div class="accordion-body bg-white small">
                                    <p class="mb-2"><strong><i class="bi bi-geo-alt text-danger me-1"></i>Lugar:</strong> <?= htmlspecialchars($r['edificio']) ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-building text-primary me-1"></i>Área asignada:</strong> <?= htmlspecialchars($r['nombre_area']) ?></p>
                                    <p class="mb-3"><strong><i class="bi bi-chat-left-text text-secondary me-1"></i>Descripción:</strong><br>
                                        <span class="text-muted"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></span>
                                    </p>
                                    <?php if ($r['evidencia_cierre']): ?>
                                        <div class="alert alert-success py-2 px-3 mb-0">
                                            <strong><i class="bi bi-check2-circle me-1"></i>Respuesta del departamento:</strong><br>
                                            <?= htmlspecialchars($r['evidencia_cierre']) ?>
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
        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold text-dark mb-0">¿Qué problema detectaste en el campus?</h5>
                <a href="?seccion=activos" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Volver</a>
            </div>
            <p class="text-muted small mb-4">Puedes reportar cualquier falla del campus (internet, cómputo, mobiliario, enjambres, etc.) y se enviará al departamento correspondiente.</p>

            <form method="POST">
                <input type="hidden" name="accion" value="crear_reporte">

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Categoría del problema</label>
                    <select name="id_categoria" id="selectCategoria" class="form-select" required>
                        <option value="" disabled selected>Elige una opción...</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id_categoria'] ?>" data-nombre="<?= htmlspecialchars(trim($c['nombre_categoria'])) ?>">
                                <?= htmlspecialchars($c['nombre_categoria']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3 caja-subcategoria d-none" id="contenedorSubcategoria">
                    <label class="form-label fw-semibold text-primary small mb-1" id="labelSubcategoria">
                        <i class="bi bi-list-check me-1"></i>Especifica el detalle
                    </label>
                    <select name="subcategoria" id="selectSubcategoria" class="form-select">
                        <option value="" disabled selected>Selecciona una opción...</option>
                    </select>

                    <div class="mt-2 d-none" id="contenedorOtroDetalle">
                        <input type="text" name="otro_detalle" id="inputOtroDetalle" class="form-control form-control-sm" placeholder="Especifica cuál (ej. abejorros, mosquitos, etc.)...">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Ubicación en el Campus</label>
                    <select name="id_ubicacion" class="form-select" required>
                        <option value="" disabled selected>¿En qué edificio o área ocurre?</option>
                        <?php foreach ($ubicaciones as $u): ?>
                            <option value="<?= $u['id_ubicacion'] ?>">
                                <?= htmlspecialchars($u['edificio'] . (!empty($u['detalle_lugar']) ? ' — ' . $u['detalle_lugar'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary" id="labelDescripcion">Pequeña descripción</label>
                    <textarea name="descripcion" id="textareaDescripcion" class="form-control" rows="4" placeholder="Cuéntanos brevemente qué sucede o en qué parte exacta (ej. aula, planta alta/baja)..." required></textarea>
                </div>

                <button type="submit" class="btn btn-itz w-100 fs-6">
                    <i class="bi bi-send-fill me-2"></i>Enviar Alerta
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const variantesMobiliario = [
        "Butaca rota o dañada",
        "Mesa o escritorio dañado",
        "Aire acondicionado ",
        "Pizarrón dañado",
        "Puerta o ventana "
    ];

    const variantesPorCategoria = {
        "Equipo de Cómputo Dañado": [
            "Computadora no enciende",
            "Monitor dañado",
            "Teclado o Mouse fallando",
            "Proyector dañado",
            "Software o sistema bloqueado"
        ],
        "Mobiliario Dañado": variantesMobiliario,
        "Mobiliario y Equipamiento Dañado": variantesMobiliario,
        "Sanitarios y Agua": [
            "Baño / WC tapado",
            "Fuga de agua ",
            "Sin suministro de agua",
            "Lavabo o mingitorio dañado"
        ],
        "Falla Eléctrica": [
            "Sin luz",
            "Lámpara o luminaria fundida",
            "Contacto o enchufe dañado"
        ],
        "Enjambre": [
            "Abejas",
            "Avispas",
            "Otro"
        ]
    };

    const selectCategoria = document.getElementById('selectCategoria');
    const contenedorSub = document.getElementById('contenedorSubcategoria');
    const selectSub = document.getElementById('selectSubcategoria');
    const labelSub = document.getElementById('labelSubcategoria');
    const contenedorOtro = document.getElementById('contenedorOtroDetalle');
    const inputOtro = document.getElementById('inputOtroDetalle');
    const textareaDesc = document.getElementById('textareaDescripcion');

    if (selectCategoria) {
        selectCategoria.addEventListener('change', function () {
            const opcionElegida = this.options[this.selectedIndex];
            const nombreCategoria = opcionElegida.getAttribute('data-nombre');
            const variantes = variantesPorCategoria[nombreCategoria] || [];

            selectSub.innerHTML = '<option value="" disabled selected>Selecciona una opción...</option>';
            contenedorOtro.classList.add('d-none');
            inputOtro.required = false;
            inputOtro.value = '';
            textareaDesc.placeholder = "Cuéntanos brevemente qué sucede o en qué parte exacta (ej. aula, planta alta/baja)...";

            if (variantes.length > 0) {
                variantes.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item;
                    opt.textContent = item;
                    selectSub.appendChild(opt);
                });
                labelSub.innerHTML = `<i class="bi bi-list-check me-1"></i>¿Qué ocurre con: ${nombreCategoria}?`;
                selectSub.required = true;
                contenedorSub.classList.remove('d-none');
            } else {
                selectSub.required = false;
                contenedorSub.classList.add('d-none');
            }
        });

        selectSub.addEventListener('change', function () {
            if (this.value === 'Otro') {
                contenedorOtro.classList.remove('d-none');
                inputOtro.required = true;
                inputOtro.focus();
                textareaDesc.placeholder = "Describe qué tipo de enjambre es, tamaño aproximado y dónde está ubicado...";
            } else {
                contenedorOtro.classList.add('d-none');
                inputOtro.required = false;
                inputOtro.value = '';
                textareaDesc.placeholder = "Cuéntanos brevemente qué sucede o en qué parte exacta (ej. aula, planta alta/baja)...";
            }
        });
    }
</script>
</body>
</html>