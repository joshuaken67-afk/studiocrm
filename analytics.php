<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/ledger.php';
require_once 'includes/financial-analytics.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$current_user = getCurrentUser();
$ledger = new LedgerManager($pdo);
$analytics = new FinancialAnalytics($pdo, $ledger);

// Get selected period
$selected_period = $_GET['period'] ?? '30_days';

// Get dashboard metrics
$dashboard_metrics = $analytics->getDashboardMetrics($selected_period);

// Get revenue analysis
$revenue_analysis = $analytics->getRevenueAnalysis();

// Get project profitability
$project_profitability = $analytics->getProjectProfitability();

// Get expense breakdown
$expense_breakdown = $analytics->getExpenseBreakdown($selected_period);

// Get client analysis
$client_analysis = $analytics->getClientAnalysis();

// Get payment method analysis
$payment_methods = $analytics->getPaymentMethodAnalysis($selected_period);

// Get cash flow analysis
$cash_flow = $analytics->getCashFlowAnalysis(6);

// Get forecasting data
$forecasting = $analytics->getFinancialForecasting(3);

// Generate insights
$insights = $analytics->generateInsights($dashboard_metrics);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Financial Analytics</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <select class="form-select" onchange="window.location.href='?period='+this.value">
                            <option value="7_days" <?php echo $selected_period === '7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30_days" <?php echo $selected_period === '30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90_days" <?php echo $selected_period === '90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="current_month" <?php echo $selected_period === 'current_month' ? 'selected' : ''; ?>>Current Month</option>
                            <option value="current_year" <?php echo $selected_period === 'current_year' ? 'selected' : ''; ?>>Current Year</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Revenue</h6>
                                    <h4>₦<?php echo number_format($dashboard_metrics['current']['total_revenue'], 2); ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-graph-up fs-2"></i>
                                </div>
                            </div>
                            <small>
                                <?php 
                                $growth = $dashboard_metrics['growth']['total_revenue'];
                                $icon = $growth >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                                $color = $growth >= 0 ? 'text-success' : 'text-warning';
                                ?>
                                <i class="bi <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                <?php echo number_format(abs($growth), 1); ?>% from previous period
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Profit</h6>
                                    <h4>₦<?php echo number_format($dashboard_metrics['current']['profit'], 2); ?></h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-currency-dollar fs-2"></i>
                                </div>
                            </div>
                            <small>
                                <?php 
                                $growth = $dashboard_metrics['growth']['profit'];
                                $icon = $growth >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                                $color = $growth >= 0 ? 'text-success' : 'text-warning';
                                ?>
                                <i class="bi <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                <?php echo number_format(abs($growth), 1); ?>% from previous period
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">ROI</h6>
                                    <h4><?php echo number_format($dashboard_metrics['current']['roi'], 1); ?>%</h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-percent fs-2"></i>
                                </div>
                            </div>
                            <small>
                                <?php 
                                $growth = $dashboard_metrics['growth']['roi'];
                                $icon = $growth >= 0 ? 'bi-arrow-up' : 'bi-arrow-down';
                                $color = $growth >= 0 ? 'text-success' : 'text-warning';
                                ?>
                                <i class="bi <?php echo $icon; ?> <?php echo $color; ?>"></i>
                                <?php echo number_format(abs($growth), 1); ?>% from previous period
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Profit Margin</h6>
                                    <h4><?php echo number_format($dashboard_metrics['current']['profit_margin'], 1); ?>%</h4>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-pie-chart fs-2"></i>
                                </div>
                            </div>
                            <small>
                                Profit as % of revenue
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insights -->
            <?php if (!empty($insights)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Financial Insights</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($insights as $insight): ?>
                            <div class="alert alert-<?php echo $insight['type'] === 'positive' ? 'success' : 'warning'; ?> alert-dismissible fade show" role="alert">
                                <strong><?php echo htmlspecialchars($insight['title']); ?></strong>
                                <?php echo htmlspecialchars($insight['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Charts Row -->
            <div class="row mb-4">
                <!-- Revenue Trend Chart -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Revenue & Expenses Trend (12 Months)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Expense Breakdown -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Expense Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Profitability -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Project Profitability Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Client</th>
                                            <th>Budget</th>
                                            <th>Revenue</th>
                                            <th>Expenses</th>
                                            <th>Profit</th>
                                            <th>Margin</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($project_profitability, 0, 10) as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                            <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                            <td>₦<?php echo number_format($project['budget'], 2); ?></td>
                                            <td>₦<?php echo number_format($project['total_revenue'], 2); ?></td>
                                            <td>₦<?php echo number_format($project['total_expenses'], 2); ?></td>
                                            <td class="<?php echo $project['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                ₦<?php echo number_format($project['profit'], 2); ?>
                                            </td>
                                            <td><?php echo number_format($project['profit_margin'], 1); ?>%</td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $project['status'] === 'completed' ? 'success' : 
                                                        ($project['status'] === 'in-progress' ? 'primary' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('-', ' ', $project['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Client Analysis & Payment Methods -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5>Top Clients by Revenue</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Projects</th>
                                            <th>Revenue</th>
                                            <th>Profit</th>
                                            <th>Avg Project Value</th>
                                            <th>Completion Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($client_analysis, 0, 8) as $client): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($client['client_name']); ?></td>
                                            <td><?php echo $client['total_projects']; ?></td>
                                            <td>₦<?php echo number_format($client['total_revenue'], 0); ?></td>
                                            <td class="<?php echo $client['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                ₦<?php echo number_format($client['profit'], 0); ?>
                                            </td>
                                            <td>₦<?php echo number_format($client['average_project_value'], 0); ?></td>
                                            <td><?php echo number_format($client['completion_rate'], 0); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($payment_methods as $method): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo $method['method_display']; ?></span>
                                <span class="badge bg-primary"><?php echo number_format($method['percentage'], 1); ?>%</span>
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar" style="width: <?php echo $method['percentage']; ?>%"></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Flow & Forecasting -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Cash Flow Analysis</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="cashFlowChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Financial Forecast (Next 3 Months)</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($forecasting['forecast'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Revenue</th>
                                            <th>Expenses</th>
                                            <th>Profit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($forecasting['forecast'] as $forecast): ?>
                                        <tr>
                                            <td><?php echo date('M Y', strtotime($forecast['month'] . '-01')); ?></td>
                                            <td>₦<?php echo number_format($forecast['forecast_revenue'], 0); ?></td>
                                            <td>₦<?php echo number_format($forecast['forecast_expenses'], 0); ?></td>
                                            <td class="<?php echo $forecast['forecast_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                ₦<?php echo number_format($forecast['forecast_profit'], 0); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Forecasts based on historical trends
                            </small>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <?php echo $forecasting['error'] ?? 'Forecasting data not available'; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Revenue Trend Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($revenue_analysis, 'month')) . "'"; ?>],
        datasets: [{
            label: 'Revenue',
            data: [<?php echo implode(',', array_column($revenue_analysis, 'revenue')); ?>],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Expenses',
            data: [<?php echo implode(',', array_column($revenue_analysis, 'expenses')); ?>],
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₦' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Expense Breakdown Chart
const expenseCtx = document.getElementById('expenseChart').getContext('2d');
const expenseChart = new Chart(expenseCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo "'" . implode("','", array_map('ucfirst', array_column($expense_breakdown, 'category'))) . "'"; ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($expense_breakdown, 'total_amount')); ?>],
            backgroundColor: [
                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Cash Flow Chart
const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
const cashFlowChart = new Chart(cashFlowCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($cash_flow, 'month')) . "'"; ?>],
        datasets: [{
            label: 'Cash In',
            data: [<?php echo implode(',', array_column($cash_flow, 'cash_in')); ?>],
            backgroundColor: '#28a745'
        }, {
            label: 'Cash Out',
            data: [<?php echo implode(',', array_column($cash_flow, 'cash_out')); ?>],
            backgroundColor: '#dc3545'
        }, {
            label: 'Net Flow',
            data: [<?php echo implode(',', array_column($cash_flow, 'net_flow')); ?>],
            type: 'line',
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: false,
                position: 'right'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
