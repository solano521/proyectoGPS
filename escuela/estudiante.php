<?php
session_start();
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: estudiante.php");
    exit();
}

if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['id_usuario'] = 1;
    $_SESSION['nombre'] = 'Yuliana';
    $_SESSION['id_rol'] = 1;
    $_SESSION['rol'] = 'Estudiante';
}

$mostrar_bienvenida = false;
if (!isset($_SESSION['bienvenida_mostrada'])) {
    $mostrar_bienvenida = true;
    $_SESSION['bienvenida_mostrada'] = true;
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

    $pdo->exec("INSERT IGNORE INTO areas_atencion (id_area, nombre_area) VALUES 
        (1, 'Centro de Cómputo'),
        (2, 'Mantenimiento e Infraestructura'),
        (3, 'Protección Civil '),
        (4, 'Recursos Materiales ')
    ");

    $pdo->exec("UPDATE categorias SET nombre_categoria = 'Mobiliario Dañado' WHERE nombre_categoria = 'Mobiliario y Equipamiento Dañado'");
    $pdo->exec("DELETE FROM categorias WHERE nombre_categoria = 'Amenaza de Bomba'");
    $pdo->exec("INSERT IGNORE INTO categorias (id_categoria, id_area, nombre_categoria) VALUES (9, 3, 'Incendio')");

    $existeFugaQuimicos = $pdo->query("SELECT COUNT(*) FROM categorias WHERE nombre_categoria = 'Fuga de Químicos'")->fetchColumn();
    if ($existeFugaQuimicos == 0) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE categorias");
        $pdo->exec("INSERT INTO categorias (id_categoria, id_area, nombre_categoria) VALUES
            (1, 1, 'Falla de Internet'),
            (2, 1, 'Equipo de Cómputo Dañado'),
            (3, 4, 'Mobiliario Dañado'),
            (4, 2, 'Sanitarios y Agua'),
            (5, 2, 'Falla Eléctrica'),
            (6, 3, 'Enjambre'),
            (7, 3, 'Fuga de Gas'),
            (8, 3, 'Fuga de Químicos'),
            (9, 3, 'Incendio')
        ");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    $existeLabBio = $pdo->query("SELECT COUNT(*) FROM ubicaciones WHERE edificio = 'Laboratorio de Bioquímica'")->fetchColumn();
    if ($existeLabBio == 0) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE ubicaciones");
        $pdo->exec("INSERT INTO ubicaciones (id_ubicacion, edificio, detalle_lugar) VALUES
            (1, 'Edificio 1', ''),
            (2, 'Edificio 2', ''),
            (3, 'Edificio 3', ''),
            (4, 'Edificio 4', ''),
            (5, 'Edificio 5', ''),
            (6, 'Edificio 6', ''),
            (7, 'Edificio 7', ''),
            (8, 'Edificio 8', ''),
            (9, 'Edificio 9', ''),
            (10, 'Edificio 10', ''),
            (11, 'Edificio 11', ''),
            (12, 'Laboratorio de Cómputo', ''),
            (13, 'Laboratorio de Bioquímica', ''),
            (14, 'Biblioteca', ''),
            (15, 'Recursos Materiales', '')
        ");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
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
        WHERE id_categoria = ? 
          AND id_ubicacion = ? 
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
$nombre_usuario = $_SESSION['nombre'];
$rol_usuario = $_SESSION['rol'] ?? 'Estudiante';
$primer_nombre = explode(' ', trim($nombre_usuario))[0];

$mensaje = "";
$vista_activa = $_GET['seccion'] ?? 'nuevo';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_reporte') {
    $id_cat = (int) $_POST['id_categoria'];
    $subcat = trim($_POST['subcategoria'] ?? '');
    $otroDetalle = trim($_POST['otro_detalle'] ?? '');
    $id_ubi = (int) $_POST['id_ubicacion'];
    $desc = trim($_POST['descripcion']);
    if ($subcat === 'Otro' && $otroDetalle !== '') {
        $subcat = "Otro: $otroDetalle";
    }

    $stmtCat = $pdo->prepare("SELECT id_area, nombre_categoria FROM categorias WHERE id_categoria = ?");
    $stmtCat->execute([$id_cat]);
    $catData = $stmtCat->fetch();

    $areaAsignada = $catData['id_area'] ?? 1;
    $nombreCategoria = $catData['nombre_categoria'] ?? 'Incidencia reportada';
    $tituloAuto = $subcat !== '' ? "$nombreCategoria ($subcat)" : $nombreCategoria;
    $descFinal = $subcat !== '' ? "Detalle específico: $subcat.\n$desc" : $desc;

    $prioAuto = calcularPrioridadAutomatica($pdo, $id_cat, $id_ubi, $nombreCategoria, $subcat, $desc);

    $folio = "ITZ-" . date("Y") . "-" . str_pad(rand(100, 9999), 4, "0", STR_PAD_LEFT);
    $stmt = $pdo->prepare("INSERT INTO reportes (folio, id_usuario_reporta, id_categoria, id_ubicacion, id_area_asignada, titulo, descripcion, prioridad) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$folio, $id_usuario, $id_cat, $id_ubi, $areaAsignada, $tituloAuto, $descFinal, $prioAuto]);

    $mensaje = "¡Alerta enviada! Tu folio de seguimiento es <strong>$folio</strong>.";
    $vista_activa = 'mis_reportes';
}

$categorias = $pdo->query("SELECT * FROM categorias WHERE nombre_categoria NOT IN ('Incendio', 'Amenaza de Bomba') ORDER BY id_categoria ASC")->fetchAll();
$ubicaciones = $pdo->query("SELECT * FROM ubicaciones WHERE edificio != 'Recursos Materiales' ORDER BY id_ubicacion ASC")->fetchAll();

$stmtMisReportes = $pdo->prepare("
    SELECT r.*, c.nombre_categoria, u.edificio, u.detalle_lugar, a.nombre_area
    FROM reportes r
    JOIN categorias c ON r.id_categoria = c.id_categoria
    JOIN ubicaciones u ON r.id_ubicacion = u.id_ubicacion
    JOIN areas_atencion a ON r.id_area_asignada = a.id_area
    WHERE r.id_usuario_reporta = ?
    ORDER BY r.fecha_creacion DESC
");
$stmtMisReportes->execute([$id_usuario]);
$misReportes = $stmtMisReportes->fetchAll();

$pendientesCount = count(array_filter($misReportes, fn($r) => $r['estatus'] !== 'Resuelto' && $r['estatus'] !== 'Cerrado'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlertaCampus ITZ | Portal Reportes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden;
        }
        #splash-screen {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, #134074 0%, #0b2545 65%, #061426 100%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            padding: 20px;
            transition: opacity 0.7s ease, transform 0.7s ease, visibility 0.7s ease;
        }
        #splash-screen.oculto {
            opacity: 0;
            transform: scale(1.04);
            visibility: hidden;
        }
        .contenedor-logo-splash {
            width: 140px;
            height: 140px;
            background: #ffffff;
            border-radius: 50%;
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            animation: entradaLogo 0.9s cubic-bezier(0.22, 1, 0.36, 1) forwards, pulsoAura 2s infinite ease-in-out 0.9s;
        }
        .contenedor-logo-splash img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }
        .texto-bienvenida {
            opacity: 0;
            transform: translateY(15px);
            animation: subirTexto 0.7s ease-out 0.4s forwards;
        }
        .barra-progreso-splash {
            width: 210px;
            height: 5px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 26px;
        }
        .barra-progreso-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #ffc107, #ffda6a);
            animation: cargarBarra 2.2s ease-in-out forwards;
        }

        @keyframes entradaLogo {
            0% { opacity: 0; transform: scale(0.5) rotate(-8deg); }
            100% { opacity: 1; transform: scale(1) rotate(0deg); }
        }
        @keyframes pulsoAura {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.55), 0 10px 30px rgba(0,0,0,0.35); }
            50% { box-shadow: 0 0 0 22px rgba(255, 193, 7, 0), 0 10px 30px rgba(0,0,0,0.35); }
        }
        @keyframes subirTexto {
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes cargarBarra {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        /* Estilos principales */
        .navbar-itz {
            background: linear-gradient(135deg, #0b2545 0%, #134074 100%);
        }
        .logo-navbar {
            width: 36px;
            height: 36px;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
            object-fit: contain;
        }
        .contenedor-principal {
            max-width: 680px;
            margin: 0 auto;
        }
        .card-app {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(11, 37, 69, 0.08);
        }
        .form-select, .form-control {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #ced4da;
        }
        .form-select:focus, .form-control:focus {
            border-color: #134074;
            box-shadow: 0 0 0 0.2rem rgba(19, 64, 116, 0.15);
        }
        .caja-subcategoria {
            background-color: #f4f8fd;
            border-left: 4px solid #134074;
            border-radius: 10px;
            padding: 12px 14px;
            animation: aparecerSuave 0.25s ease-out;
        }
        @keyframes aparecerSuave {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-itz {
            background-color: #134074;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            color: #fff;
        }
        .btn-itz:hover {
            background-color: #0b2545;
            color: #fff;
        }
        .accordion-item {
            border: 1px solid #e9ecef;
            border-radius: 12px !important;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .accordion-button:not(.collapsed) {
            background-color: #eef4fc;
            color: #0b2545;
        }
    </style>
</head>
<body>

<?php if ($mostrar_bienvenida): ?>
<div id="splash-screen">
    <div class="contenedor-logo-splash">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQOusK5rsOeBHWjKgx-jqWZw6QYdq3FgRVNC2nJqVIlRAxdC6ZgL_b22s4&s=10" alt="Logo ITZ AlertaCampus">
    </div>
    <div class="texto-bienvenida">
        <h2 class="fw-bold mb-1">¡Bienvenido, <?= htmlspecialchars($primer_nombre) ?>!</h2>
        <p class="text-warning fw-semibold mb-0 fs-5">AlertaCampus ITZ</p>
        <small class="text-white-50">Sistema de Reportes e Incidencias</small>
    </div>
    <div class="barra-progreso-splash">
        <div class="barra-progreso-fill"></div>
    </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-dark navbar-itz py-3 mb-4 shadow-sm">
    <div class="container contenedor-principal d-flex justify-content-between align-items-center">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2 mb-0" href="?seccion=nuevo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQOusK5rsOeBHWjKgx-jqWZw6QYdq3FgRVNC2nJqVIlRAxdC6ZgL_b22s4&s=10" alt="Logo" class="logo-navbar">
            <div>
                <span class="d-block lh-1">AlertaCampus <small class="fw-normal opacity-75">ITZ</small></span>
                <small class="text-warning fw-normal" style="font-size: 0.72rem;"><?= htmlspecialchars($rol_usuario) ?></small>
            </div>
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="?seccion=mis_reportes" class="btn btn-sm <?= $vista_activa === 'mis_reportes' ? 'btn-warning text-dark fw-bold' : 'btn-outline-light' ?> rounded-pill px-3 py-2 d-flex align-items-center gap-1">
                <i class="bi bi-clock-history"></i>
                <span class="d-none d-sm-inline">Pendientes</span>
                <?php if ($pendientesCount > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill ms-1"><?= $pendientesCount ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-1 rounded-pill px-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-list fs-5"></i>
                    <span>Reportes</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                    <li class="px-3 py-2 border-bottom">
                        <small class="text-muted d-block"><?= htmlspecialchars($rol_usuario) ?></small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($nombre_usuario) ?></span>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 <?= $vista_activa === 'nuevo' ? 'active' : '' ?>" href="?seccion=nuevo">
                            <i class="bi bi-plus-circle me-2"></i>Levantar nuevo reporte
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 <?= $vista_activa === 'mis_reportes' ? 'active' : '' ?>" href="?seccion=mis_reportes">
                            <i class="bi bi-folder2-open me-2"></i>Mis reportes (<?= count($misReportes) ?>)
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger fw-semibold" href="?logout=1">
                            <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container contenedor-principal pb-5">

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($vista_activa === 'nuevo'): ?>
        <div class="card card-app p-4">
            <h5 class="fw-bold text-dark mb-1">¿Qué problema detectaste en el campus?</h5>
            <p class="text-muted small mb-4">Selecciona la categoría, especifica si aplica, indica el lugar y describe brevemente.</p>

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

    <?php else: ?>
        <div class="card card-app p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Mis Reportes</h5>
                    <small class="text-muted">Toca un reporte para ver su estatus y seguimiento</small>
                </div>
                <a href="?seccion=nuevo" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo
                </a>
            </div>

            <?php if (empty($misReportes)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-3">Aún no has enviado ningún reporte.</p>
                    <a href="?seccion=nuevo" class="btn btn-itz btn-sm px-4">Crear mi primer reporte</a>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionReportes">
                    <?php foreach ($misReportes as $index => $r): ?>
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
                                <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?> py-3" type="button" data-bs-toggle="collapse" data-bs-target="#rep<?= $r['id_reporte'] ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center pe-2">
                                        <div>
                                            <span class="fw-bold text-primary d-block"><?= htmlspecialchars($r['titulo']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($r['folio']) ?> • <?= date('d/m/Y H:i', strtotime($r['fecha_creacion'])) ?></small>
                                        </div>
                                        <span class="badge <?= $badgeColor ?> rounded-pill px-3 py-2"><?= $r['estatus'] ?></span>
                                    </div>
                                </button>
                            </h2>
                            <div id="rep<?= $r['id_reporte'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#accordionReportes">
                                <div class="accordion-body bg-white">
                                    <p class="mb-2"><strong><i class="bi bi-geo-alt text-danger me-1"></i>Lugar:</strong> <?= htmlspecialchars($r['edificio'] . (!empty($r['detalle_lugar']) ? ' - ' . $r['detalle_lugar'] : '')) ?></p>
                                    <p class="mb-2"><strong><i class="bi bi-building text-primary me-1"></i>Área que atiende:</strong> <?= htmlspecialchars($r['nombre_area']) ?></p>
                                    <p class="mb-3"><strong><i class="bi bi-chat-left-text text-secondary me-1"></i>Tu descripción:</strong><br>
                                        <span class="text-muted"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></span>
                                    </p>

                                    <?php if ($r['evidencia_cierre']): ?>
                                        <div class="alert alert-success py-2 px-3 mb-0 small">
                                            <strong><i class="bi bi-check2-circle me-1"></i>Actualización del departamento:</strong><br>
                                            <?= htmlspecialchars($r['evidencia_cierre']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small border-top pt-2">
                                            <i class="bi bi-info-circle me-1"></i>
                                            <?= $r['estatus'] === 'Pendiente' ? 'Tu reporte ya fue enviado al departamento correspondiente.' : 'El departamento ya está atendiendo tu reporte.' ?>
                                        </div>
                                    <?php endif; ?>
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
<script>
    const variantesMobiliario = [
        "Butaca rota o dañada",
        "Mesa o escritorio dañado",
        "Aire acondicionado ",
        "Pizarrón dañado",
        "Puerta ventana "
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

<?php if ($mostrar_bienvenida): ?>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const splash = document.getElementById('splash-screen');
            if (splash) {
                splash.classList.add('oculto');
                setTimeout(() => splash.remove(), 700);
            }
        }, 2300);
    });
</script>
<?php endif; ?>
</body>
</html>