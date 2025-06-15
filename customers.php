<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'backend/config/database.php';
require_once 'includes/cache_control.php';

try {
    $db = getDB();
    $business_id = $_SESSION['business_id'];
    
    // Cargar clientes
    $customers = $db->fetchAll(
        "SELECT c.*, 
         (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as total_sales,
         (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE customer_id = c.id) as total_spent
         FROM customers c 
         WHERE c.business_id = ? AND c.status = 1 
         ORDER BY c.first_name ASC",
        [$business_id]
    );
    
    // Estadísticas
    $stats = $db->single(
        "SELECT COUNT(*) as total_customers FROM customers WHERE business_id = ? AND status = 1",
        [$business_id]
    );
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $customers = [];
    $stats = ['total_customers' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Treinta</title>
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
                <h1 class="page-title">Clientes</h1>
            </div>
            <div class="header-actions">
                <button class="btn btn-success" onclick="openCustomerModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo Cliente
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
                        <div class="stat-label">Total Clientes</div>
                        <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de clientes -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lista de Clientes</h3>
                <span class="badge badge-gray"><?php echo count($customers); ?> clientes</span>
            </div>
            <div class="card-content">
                <?php if (empty($customers)): ?>
                    <div class="empty-state">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <h3>No hay clientes registrados</h3>
                        <p>Comienza agregando tu primer cliente</p>
                        <button class="btn btn-primary" onclick="openCustomerModal()">Agregar Cliente</button>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Documento</th>
                                    <th>Ventas</th>
                                    <th>Total Gastado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;">
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['document_number'] ?: '-'); ?></td>
                                    <td><?php echo $customer['total_sales']; ?></td>
                                    <td class="font-mono">S/ <?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn-icon edit" onclick="editCustomer(<?php echo $customer['id']; ?>)" title="Editar">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                            </button>
                                            <button class="btn-icon delete" onclick="deleteCustomer(<?php echo $customer['id']; ?>)" title="Eliminar">
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
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de Cliente -->
    <div class="modal-overlay" id="customerModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo Cliente</h3>
                <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="customerForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="first_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="last_name" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="phone" class="form-input">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-gray" onclick="closeCustomerModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCustomerModal() {
            document.getElementById('customerModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeCustomerModal() {
            document.getElementById('customerModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        function editCustomer(id) {
            alert('Editar cliente ID: ' + id + ' (funcionalidad en desarrollo)');
        }

        function deleteCustomer(id) {
            if (confirm('¿Eliminar cliente?')) {
                alert('Eliminar cliente ID: ' + id + ' (funcionalidad en desarrollo)');
            }
        }
    </script>

</body>
</html>