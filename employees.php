<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2);
}

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar empleados
    $employees = $db->fetchAll(
        "SELECT e.*, u.email, u.last_login, u.status as user_status,
         (SELECT COUNT(*) FROM sales WHERE user_id = u.id) as total_sales,
         (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE user_id = u.id) as total_sales_amount
         FROM employees e
         LEFT JOIN users u ON e.user_id = u.id
         WHERE e.business_id = ? AND e.status = 'active'
         ORDER BY e.first_name ASC",
        [$business_id]
    );
    
    // Estadísticas
    $stats = $db->single(
        "SELECT 
         COUNT(*) as total_employees,
         COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
         COALESCE(AVG(salary), 0) as avg_salary
         FROM employees 
         WHERE business_id = ?",
        [$business_id]
    );
    
    // Top vendedores del mes
    $topSellers = $db->fetchAll(
        "SELECT e.first_name, e.last_name, e.position,
         COUNT(s.id) as sales_count,
         COALESCE(SUM(s.total_amount), 0) as total_sales
         FROM employees e
         LEFT JOIN users u ON e.user_id = u.id
         LEFT JOIN sales s ON u.id = s.user_id AND MONTH(s.sale_date) = MONTH(CURRENT_DATE())
         WHERE e.business_id = ? AND e.status = 'active'
         GROUP BY e.id
         ORDER BY total_sales DESC
         LIMIT 5",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $employees = [];
    $stats = ['total_employees' => 0, 'active_employees' => 0, 'avg_salary' => 0];
    $topSellers = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - Treinta</title>
    <?php includeCss('assets/css/style.css'); ?>
</head>
<body class="dashboard-page">

    <?php include 'includes/slidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <div class="header-left">
                <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1 class="page-title">Empleados</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openEmployeeModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Empleado
                </button>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Total Empleados</div>
                        <div class="stat-value"><?php echo $stats['total_employees']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change"><?php echo $stats['active_employees']; ?> activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Salario Promedio</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['avg_salary']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change">Mensual</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Top vendedores -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Vendedores del Mes</h3>
                    <span class="badge badge-gray"><?php echo date('M Y'); ?></span>
                </div>
                <div class="card-content">
                    <?php if (empty($topSellers)): ?>
                        <p class="text-center text-gray-500">No hay datos de ventas este mes</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($topSellers as $index => $seller): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background-color: var(--gray-50); border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="width: 32px; height: 32px; background-color: var(--primary-500); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;">
                                                <?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--gray-500);">
                                                <?php echo htmlspecialchars($seller['position'] ?: 'Empleado'); ?> • 
                                                <?php echo $seller['sales_count']; ?> ventas
                                            </div>
                                        </div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-weight: 700; color: var(--success-600);">
                                        <?php echo formatCurrency($seller['total_sales']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista rápida de empleados -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Empleados Activos</h3>
                    <span class="badge badge-success"><?php echo count($employees); ?></span>
                </div>
                <div class="card-content">
                    <?php if (empty($employees)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>No hay empleados registrados</h3>
                            <p>Comienza agregando tu primer empleado</p>
                            <button class="btn btn-primary" onclick="openEmployeeModal()">Agregar Empleado</button>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                            <?php foreach (array_slice($employees, 0, 8) as $employee): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border: 1px solid var(--gray-200); border-radius: 8px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);">
                                            <?php echo htmlspecialchars($employee['position'] ?: 'Empleado'); ?>
                                            <?php if ($employee['department']): ?>
                                                • <?php echo htmlspecialchars($employee['department']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-400);">
                                            <?php echo $employee['total_sales']; ?> ventas • 
                                            <?php echo formatCurrency($employee['total_sales_amount']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <?php if ($employee['user_status'] == 1): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-gray">Inactivo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1rem; text-align: center;">
                            <button class="btn btn-gray" onclick="showAllEmployees()">Ver Todos los Empleados</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla completa (inicialmente oculta) -->
        <div class="card hidden" id="allEmployeesCard">
            <div class="card-header">
                <h3 class="card-title">Todos los Empleados</h3>
                <button class="btn btn-gray" onclick="hideAllEmployees()">Ocultar</button>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empleado</th>
                                <th>Email</th>
                                <th>Cargo</th>
                                <th>Departamento</th>
                                <th>Salario</th>
                                <th>Fecha Ingreso</th>
                                <th>Ventas</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </div>
                                    <?php if ($employee['phone']): ?>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);">
                                            <?php echo htmlspecialchars($employee['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($employee['email'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($employee['position'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($employee['department'] ?: '-'); ?></td>
                                <td class="font-mono">
                                    <?php echo $employee['salary'] ? formatCurrency($employee['salary']) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $employee['hire_date'] ? date('d/m/Y', strtotime($employee['hire_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <div><?php echo $employee['total_sales']; ?> ventas</div>
                                    <div class="font-mono font-small text-gray-500">
                                        <?php echo formatCurrency($employee['total_sales_amount']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($employee['status'] === 'active'): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php elseif ($employee['status'] === 'inactive'): ?>
                                        <span class="badge badge-warning">Inactivo</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Terminado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn-icon edit" onclick="editEmployee(<?php echo $employee['id']; ?>)" title="Editar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon delete" onclick="deactivateEmployee(<?php echo $employee['id']; ?>)" title="Desactivar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="15" y1="9" x2="9" y2="15"/>
                                                <line x1="9" y1="9" x2="15" y2="15"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Empleado -->
    <div class="modal-overlay" id="employeeModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Empleado</h3>
                <button class="modal-close" onclick="closeEmployeeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="first_name" class="form-input" required placeholder="Juan">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="last_name" class="form-input" required placeholder="Pérez">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" placeholder="juan@email.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="phone" class="form-input" placeholder="999 999 999">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Documento</label>
                            <input type="text" name="document_number" class="form-input" placeholder="DNI, Pasaporte, etc.">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cargo</label>
                            <input type="text" name="position" class="form-input" placeholder="Vendedor, Cajero, etc.">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Departamento</label>
                            <select name="department" class="form-input">
                                <option value="">Seleccionar departamento</option>
                                <option value="Ventas">Ventas</option>
                                <option value="Administración">Administración</option>
                                <option value="Inventario">Inventario</option>
                                <option value="Atención al Cliente">Atención al Cliente</option>
                                <option value="Marketing">Marketing</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha de Ingreso</label>
                            <input type="date" name="hire_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Salario Mensual</label>
                            <input type="number" name="salary" class="form-input" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Comisión (%)</label>
                            <input type="number" name="commission_rate" class="form-input" step="0.01" min="0" max="100" placeholder="0.00">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-input" rows="3" placeholder="Información adicional del empleado..."></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeEmployeeModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Empleado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEmployeeModal() {
            document.getElementById('employeeModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEmployeeModal() {
            document.getElementById('employeeModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('employeeForm').reset();
        }

        function showAllEmployees() {
            document.getElementById('allEmployeesCard').classList.remove('hidden');
            document.getElementById('allEmployeesCard').scrollIntoView({ behavior: 'smooth' });
        }

        function hideAllEmployees() {
            document.getElementById('allEmployeesCard').classList.add('hidden');
        }

        function editEmployee(id) {
            alert('Editar empleado ID: ' + id + ' (funcionalidad en desarrollo)');
        }

        function deactivateEmployee(id) {
            if (confirm('¿Desactivar este empleado?')) {
                alert('Desactivar empleado ID: ' + id + ' (funcionalidad en desarrollo)');
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmployeeModal();
            }
        });
    </script>

</body>
</html>