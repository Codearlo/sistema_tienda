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
    
    // Cargar deudas por cobrar
    $receivables = $db->fetchAll(
        "SELECT d.*, c.first_name, c.last_name, c.phone, s.sale_number
         FROM debts d
         LEFT JOIN customers c ON d.customer_id = c.id
         LEFT JOIN sales s ON d.sale_id = s.id
         WHERE d.business_id = ? AND d.type = 'receivable' AND d.status != 'paid'
         ORDER BY d.due_date ASC",
        [$business_id]
    );
    
    // Cargar deudas por pagar
    $payables = $db->fetchAll(
        "SELECT d.*, s.company_name as supplier_name
         FROM debts d
         LEFT JOIN suppliers s ON d.supplier_id = s.id
         WHERE d.business_id = ? AND d.type = 'payable' AND d.status != 'paid'
         ORDER BY d.due_date ASC",
        [$business_id]
    );
    
    // Estadísticas
    $stats = $db->single(
        "SELECT 
         COALESCE(SUM(CASE WHEN type = 'receivable' AND status != 'paid' THEN remaining_amount ELSE 0 END), 0) as total_receivables,
         COALESCE(SUM(CASE WHEN type = 'payable' AND status != 'paid' THEN remaining_amount ELSE 0 END), 0) as total_payables,
         COUNT(CASE WHEN type = 'receivable' AND status != 'paid' THEN 1 END) as count_receivables,
         COUNT(CASE WHEN type = 'payable' AND status != 'paid' THEN 1 END) as count_payables,
         COUNT(CASE WHEN due_date < CURDATE() AND status != 'paid' THEN 1 END) as overdue_count
         FROM debts 
         WHERE business_id = ?",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $receivables = [];
    $payables = [];
    $stats = [
        'total_receivables' => 0, 'total_payables' => 0, 
        'count_receivables' => 0, 'count_payables' => 0, 'overdue_count' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deudas - Treinta</title>
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
                <h1 class="page-title">Cuentas por Cobrar y Pagar</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openDebtModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nueva Deuda
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
                        <div class="stat-label">Por Cobrar</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['total_receivables']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-positive"><?php echo $stats['count_receivables']; ?> cuentas</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Por Pagar</div>
                        <div class="stat-value"><?php echo formatCurrency($stats['total_payables']); ?></div>
                    </div>
                    <div class="stat-icon stat-icon-error">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-negative"><?php echo $stats['count_payables']; ?> cuentas</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Vencidas</div>
                        <div class="stat-value"><?php echo $stats['overdue_count']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change stat-change-negative">Requieren atención</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <div class="stat-label">Balance</div>
                        <div class="stat-value">
                            <?php 
                            $balance = $stats['total_receivables'] - $stats['total_payables'];
                            echo formatCurrency($balance); 
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon <?php echo $balance >= 0 ? 'stat-icon-success' : 'stat-icon-error'; ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-change <?php echo $balance >= 0 ? 'stat-change-positive' : 'stat-change-negative'; ?>">
                    <?php echo $balance >= 0 ? 'Positivo' : 'Negativo'; ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Cuentas por Cobrar -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cuentas por Cobrar</h3>
                    <span class="badge badge-success"><?php echo count($receivables); ?></span>
                </div>
                <div class="card-content">
                    <?php if (empty($receivables)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <h3>No hay cuentas por cobrar</h3>
                            <p>Todas las ventas están al día</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                            <?php foreach ($receivables as $debt): ?>
                                <?php 
                                $isOverdue = strtotime($debt['due_date']) < time();
                                $daysUntilDue = ceil((strtotime($debt['due_date']) - time()) / (60 * 60 * 24));
                                ?>
                                <div class="debt-item <?php echo $isOverdue ? 'debt-item-overdue' : 'debt-item-pending'; ?>">
                                    <div class="debt-info">
                                        <div class="debt-customer">
                                            <?php echo htmlspecialchars($debt['first_name'] . ' ' . $debt['last_name']); ?>
                                        </div>
                                        <div class="debt-description">
                                            <?php echo htmlspecialchars($debt['description']); ?>
                                        </div>
                                        <div class="debt-date">
                                            <?php if ($isOverdue): ?>
                                                <span class="text-red-600">Venció hace <?php echo abs($daysUntilDue); ?> días</span>
                                            <?php else: ?>
                                                <span>Vence en <?php echo $daysUntilDue; ?> días</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="debt-amount-info">
                                        <div class="debt-amount"><?php echo formatCurrency($debt['remaining_amount']); ?></div>
                                        <div class="debt-actions">
                                            <button class="btn btn-success btn-small" onclick="recordPayment(<?php echo $debt['id']; ?>)">
                                                Cobrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cuentas por Pagar -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cuentas por Pagar</h3>
                    <span class="badge badge-error"><?php echo count($payables); ?></span>
                </div>
                <div class="card-content">
                    <?php if (empty($payables)): ?>
                        <div class="empty-state">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <h3>No hay cuentas por pagar</h3>
                            <p>No tienes deudas pendientes</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                            <?php foreach ($payables as $debt): ?>
                                <?php 
                                $isOverdue = strtotime($debt['due_date']) < time();
                                $daysUntilDue = ceil((strtotime($debt['due_date']) - time()) / (60 * 60 * 24));
                                ?>
                                <div class="debt-item <?php echo $isOverdue ? 'debt-item-overdue' : 'debt-item-pending'; ?>">
                                    <div class="debt-info">
                                        <div class="debt-customer">
                                            <?php echo htmlspecialchars($debt['supplier_name'] ?: 'Proveedor'); ?>
                                        </div>
                                        <div class="debt-description">
                                            <?php echo htmlspecialchars($debt['description']); ?>
                                        </div>
                                        <div class="debt-date">
                                            <?php if ($isOverdue): ?>
                                                <span class="text-red-600">Venció hace <?php echo abs($daysUntilDue); ?> días</span>
                                            <?php else: ?>
                                                <span>Vence en <?php echo $daysUntilDue; ?> días</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="debt-amount-info">
                                        <div class="debt-amount"><?php echo formatCurrency($debt['remaining_amount']); ?></div>
                                        <div class="debt-actions">
                                            <button class="btn btn-primary btn-small" onclick="recordPayment(<?php echo $debt['id']; ?>)">
                                                Pagar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Nueva Deuda -->
    <div class="modal-overlay" id="debtModal">
        <div class="modal modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Nueva Deuda</h3>
                <button class="modal-close" onclick="closeDebtModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="debtForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-input" required onchange="toggleDebtType()">
                                <option value="">Seleccionar tipo</option>
                                <option value="receivable">Por Cobrar</option>
                                <option value="payable">Por Pagar</option>
                            </select>
                        </div>
                        <div class="form-group" id="customerGroup" style="display: none;">
                            <label class="form-label">Cliente</label>
                            <select name="customer_id" class="form-input">
                                <option value="">Seleccionar cliente</option>
                                <!-- Se llenarían con PHP desde la BD -->
                            </select>
                        </div>
                        <div class="form-group" id="supplierGroup" style="display: none;">
                            <label class="form-label">Proveedor</label>
                            <select name="supplier_id" class="form-input">
                                <option value="">Seleccionar proveedor</option>
                                <!-- Se llenarían con PHP desde la BD -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="description" class="form-input" required placeholder="Concepto de la deuda">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Monto Original</label>
                            <input type="number" name="original_amount" class="form-input" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha de Vencimiento</label>
                            <input type="date" name="due_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prioridad</label>
                            <select name="priority" class="form-input">
                                <option value="medium">Media</option>
                                <option value="low">Baja</option>
                                <option value="high">Alta</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-input" rows="3" placeholder="Notas adicionales..."></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeDebtModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar Deuda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Pago -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Pago</h3>
                <button class="modal-close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="debtId" name="debt_id">
                    <div class="form-group">
                        <label class="form-label">Monto del Pago</label>
                        <input type="number" name="amount" class="form-input" step="0.01" min="0" required placeholder="0.00">
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
                        <label class="form-label">Fecha de Pago</label>
                        <input type="date" name="payment_date" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="reference_number" class="form-input" placeholder="Número de referencia">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closePaymentModal()">Cancelar</button>
                        <button type="submit" class="btn btn-success">Registrar Pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .debt-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            transition: var(--transition-fast);
        }

        .debt-item:hover {
            box-shadow: var(--shadow-sm);
            transform: translateY(-1px);
        }

        .debt-item-pending {
            background-color: var(--gray-50);
            border-left: 4px solid var(--primary-500);
        }

        .debt-item-overdue {
            background-color: var(--error-50);
            border-left: 4px solid var(--error-500);
        }

        .debt-info {
            flex: 1;
        }

        .debt-customer {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .debt-description {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }

        .debt-date {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .debt-amount-info {
            text-align: right;
        }

        .debt-amount {
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .debt-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>

    <script>
        function openDebtModal() {
            document.getElementById('debtModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeDebtModal() {
            document.getElementById('debtModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('debtForm').reset();
            document.getElementById('customerGroup').style.display = 'none';
            document.getElementById('supplierGroup').style.display = 'none';
        }

        function openPaymentModal() {
            document.getElementById('paymentModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('paymentForm').reset();
        }

        function toggleDebtType() {
            const type = document.querySelector('[name="type"]').value;
            const customerGroup = document.getElementById('customerGroup');
            const supplierGroup = document.getElementById('supplierGroup');
            
            if (type === 'receivable') {
                customerGroup.style.display = 'block';
                supplierGroup.style.display = 'none';
            } else if (type === 'payable') {
                customerGroup.style.display = 'none';
                supplierGroup.style.display = 'block';
            } else {
                customerGroup.style.display = 'none';
                supplierGroup.style.display = 'none';
            }
        }

        function recordPayment(debtId) {
            document.getElementById('debtId').value = debtId;
            openPaymentModal();
        }

        // Cerrar modales al hacer clic fuera
        document.getElementById('debtModal').addEventListener('click', function(e) {
            if (e.target === this) closeDebtModal();
        });

        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) closePaymentModal();
        });
    </script>

</body>
</html>