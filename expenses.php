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
    
    // Cargar gastos
    $expenses = $db->fetchAll(
        "SELECT e.*, s.company_name as supplier_name, u.first_name as user_name
         FROM expenses e
         LEFT JOIN suppliers s ON e.supplier_id = s.id
         LEFT JOIN users u ON e.user_id = u.id
         WHERE e.business_id = ? AND e.status = 1
         ORDER BY e.expense_date DESC
         LIMIT 50",
        [$business_id]
    );
    
    // Estadísticas del mes
    $currentMonth = date('Y-m');
    $stats = $db->single(
        "SELECT 
         COUNT(*) as total_expenses,
         COALESCE(SUM(amount), 0) as total_amount,
         COALESCE(AVG(amount), 0) as avg_expense
         FROM expenses 
         WHERE business_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ? AND status = 1",
        [$business_id, $currentMonth]
    );
    
    // Gastos por categoría
    $categories = $db->fetchAll(
        "SELECT category, COUNT(*) as count, SUM(amount) as total
         FROM expenses 
         WHERE business_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ? AND status = 1
         GROUP BY category
         ORDER BY total DESC",
        [$business_id, $currentMonth]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $expenses = [];
    $stats = ['total_expenses' => 0, 'total_amount' => 0, 'avg_expense' => 0];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gastos - Treinta</title>
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
                <h1 class="page-title">Gastos</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openExpenseModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Gasto
                </button>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Estadísticas del mes -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Gastos Este Mes</div>
                        <div class="stat-value"><?php echo $stats['total_expenses']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Total Gastado</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['total_amount']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-error">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Gasto Promedio</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['avg_expense']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Gastos por categoría -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Gastos por Categoría</h3>
                    <span class="badge badge-gray"><?php echo date('M Y'); ?></span>
                </div>
                <div class="card-content">
                    <?php if (empty($categories)): ?>
                        <p class="text-center text-gray-500">No hay gastos este mes</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($categories as $category): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background-color: var(--gray-50); border-radius: 8px;">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($category['category']); ?></div>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);"><?php echo $category['count']; ?> gastos</div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-weight: 700; color: var(--error-600);">
                                        <?php echo formatCurrency($category['total']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista de gastos recientes -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Gastos Recientes</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($expenses)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <h3>No hay gastos registrados</h3>
                            <p>Comienza registrando tu primer gasto</p>
                            <button class="btn btn-primary" onclick="openExpenseModal()">Registrar Gasto</button>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                            <?php foreach (array_slice($expenses, 0, 10) as $expense): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border: 1px solid var(--gray-200); border-radius: 8px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                            <?php echo htmlspecialchars($expense['description']); ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);">
                                            <?php echo htmlspecialchars($expense['category']); ?> • 
                                            <?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?>
                                        </div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-weight: 700; color: var(--error-600);">
                                        <?php echo formatCurrency($expense['amount']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 1rem; text-align: center;">
                            <button class="btn btn-gray" onclick="showAllExpenses()">Ver Todos los Gastos</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabla completa (inicialmente oculta) -->
        <div class="card hidden" id="allExpensesCard">
            <div class="card-header">
                <h3 class="card-title">Todos los Gastos</h3>
                <button class="btn btn-gray" onclick="hideAllExpenses()">Ocultar</button>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Monto</th>
                                <th>Método Pago</th>
                                <th>Proveedor</th>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($expense['description']); ?></div>
                                    <?php if ($expense['reference_number']): ?>
                                        <div style="font-size: 0.875rem; color: var(--gray-500);">
                                            Ref: <?php echo htmlspecialchars($expense['reference_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-gray"><?php echo htmlspecialchars($expense['category']); ?></span>
                                </td>
                                <td class="font-mono font-bold text-red-600"><?php echo formatCurrency($expense['amount']); ?></td>
                                <td><?php echo ucfirst($expense['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($expense['supplier_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($expense['user_name']); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn-icon edit" onclick="editExpense(<?php echo $expense['id']; ?>)" title="Editar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon delete" onclick="deleteExpense(<?php echo $expense['id']; ?>)" title="Eliminar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3,6 5,6 21,6"/>
                                                <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
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

    <!-- Modal de Gasto -->
    <div class="modal-overlay" id="expenseModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Gasto</h3>
                <button class="modal-close" onclick="closeExpenseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="expenseForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="description" class="form-input" required placeholder="Ej: Compra de materiales">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="category" class="form-input" required>
                                <option value="">Seleccionar categoría</option>
                                <option value="Materiales">Materiales</option>
                                <option value="Servicios">Servicios</option>
                                <option value="Transporte">Transporte</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Oficina">Oficina</option>
                                <option value="Impuestos">Impuestos</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monto</label>
                            <input type="number" name="amount" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="expense_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Método de Pago</label>
                            <select name="payment_method" class="form-input" required>
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">Transferencia</option>
                                <option value="check">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número de Referencia</label>
                            <input type="text" name="reference_number" class="form-input" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeExpenseModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Gasto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openExpenseModal() {
            document.getElementById('expenseModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('expenseForm').reset();
        }

        function showAllExpenses() {
            document.getElementById('allExpensesCard').classList.remove('hidden');
            document.getElementById('allExpensesCard').scrollIntoView({ behavior: 'smooth' });
        }

        function hideAllExpenses() {
            document.getElementById('allExpensesCard').classList.add('hidden');
        }

        function editExpense(id) {
            alert('Editar gasto ID: ' + id + ' (funcionalidad en desarrollo)');
        }

        function deleteExpense(id) {
            if (confirm('¿Eliminar este gasto?')) {
                alert('Eliminar gasto ID: ' + id + ' (funcionalidad en desarrollo)');
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('expenseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExpenseModal();
            }
        });
    </script>

</body>
</html>