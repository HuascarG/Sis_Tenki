<?php
// Vista/backups/index.php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';

// Área Restringida a Administradores (Crítico)
requireAdmin();

$pageTitle = 'Mantenimiento del Sistema';
include ROOT . '/Vista/Includes/header.php';
?>

<div class="row align-items-center mb-4">
    <div class="col-12">
        <h3 class="mb-1 text-dark fw-bold"><i class="bi bi-hdd-network me-2"></i>Mantenimiento y Respaldo</h3>
        <p class="text-muted mb-0">Genera instantáneas completas de tu información contable, o restaura la base de datos a un estado anterior en caso de emergencia.</p>
    </div>
</div>

<div class="row g-4">
    
    <!-- TARJETA GENERAR RESPALDO -->
    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--primary-color) !important;">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <i class="bi bi-cloud-arrow-down text-primary" style="font-size: 3.5rem;"></i>
                </div>
                <h4 class="fw-bold mb-2">Generar Respaldo Local</h4>
                <p class="text-muted mb-4 px-3">
                    Obtén una copia exacta en tiempo real de todos tus clientes, inventarios valorados, proveedores y transacciones. El archivo pesará muy poco y podrá ser guardado en cualquier memoria USB o Nube.
                </p>
                <a href="<?= BASE_URL ?>Vista/backups/procesar.php?action=download" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm">
                    <i class="bi bi-download me-2"></i>Descargar Archivo (.sql)
                </a>
            </div>
            <div class="card-footer bg-transparent border-0 text-center pb-4 pt-0 text-muted" style="font-size: 0.85rem;">
                <i class="bi bi-shield-check me-1"></i>Conexión encriptada vía MySQL nativo.
            </div>
        </div>
    </div>
    
    <!-- TARJETA RESTAURAR SISTEMA (DANGER ZONE) -->
    <div class="col-12 col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-top: 4px solid var(--danger-color) !important;">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3.5rem;"></i>
                </div>
                <h4 class="fw-bold mb-2 text-center text-danger">Restaurar Base de Datos</h4>
                <p class="text-muted mb-4 text-center px-1">
                    Cargar un archivo .sql borrará irrevocablemente los movimientos realizados del presente para devolver el sistema a la fecha del archivo seleccionado. Es un acto altamente destructivo si te equivocas de documento.
                </p>
                
                <form action="<?= BASE_URL ?>Vista/backups/procesar.php" method="POST" enctype="multipart/form-data" id="form-restaurar">
                    <div class="mb-3">
                        <label class="form-label fw-semi">Sube el archivo de Backup (.sql)</label>
                        <input type="file" name="backup_file" id="backup_file" class="form-control" accept=".sql" required>
                    </div>
                    
                    <div class="alert alert-warning mb-3 d-flex align-items-start gap-2" style="font-size: 0.85rem;">
                        <i class="bi bi-radioactive fs-5"></i>
                        <div>
                            <strong>Seguridad de Candado:</strong> Escribe la palabra <code>RESTAURAR</code> en mayúsculas en el recuadro inferior para desbloquear el sistema.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <input type="text" name="candado_seguridad" id="candado_seguridad" class="form-control bg-light border-danger text-center" placeholder="Escribe RESTAURAR aquí" required autocomplete="off">
                    </div>
                    
                    <button type="submit" class="btn btn-danger btn-lg w-100 rounded-pill shadow-sm" id="btn-submit-restaurar" disabled>
                        <i class="bi bi-server me-2"></i>Sobrescribir el Sistema Ahora
                    </button>
                    
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputCandado = document.getElementById('candado_seguridad');
    const btnRestaurar = document.getElementById('btn-submit-restaurar');
    const fileInput = document.getElementById('backup_file');
    
    // Función central de apertura de candado
    const checkCandado = () => {
        // Solo habilitar si escribió RESTAURAR y si subió un archivo
        if (inputCandado.value.trim() === 'RESTAURAR' && fileInput.files.length > 0) {
            btnRestaurar.disabled = false;
        } else {
            btnRestaurar.disabled = true;
        }
    };

    // Vigilar que el usuario escriba
    inputCandado.addEventListener('input', checkCandado);
    
    // Vigilar que adjunte archivo
    fileInput.addEventListener('change', checkCandado);
    
    // Alerta inmensa en el momento de apretar el gatillo (SweetAlert2)
    document.getElementById('form-restaurar').addEventListener('submit', (e) => {
        e.preventDefault(); // Siempre detener primero
        const form = e.target;
        
        Swal.fire({
            icon: 'error',
            title: '⚠️ ADVERTENCIA CRÍTICA',
            html: '<strong>¿Estás absolutamente seguro de REEMPLAZAR toda la base de datos de TENKI?</strong><br><br>Cualquier factura, inventario o cliente creado <u>después</u> de este archivo de respaldo: <span class="text-danger fw-bold">DESAPARECERÁ PARA SIEMPRE.</span>',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-radioactive me-1"></i>Sí, sobrescribir todo',
            cancelButtonText: 'No, cancelar',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit(); // Solo enviar si confirma
            }
        });
    });
});
</script>

<?php include ROOT . '/Vista/Includes/footer.php'; ?>
