<?php
/**
 * Enhanced Financial Analytics System
 */

class FinancialAnalytics {
    private $pdo;
    private $ledger;
    
    public function __construct($pdo, $ledger) {
        $this->pdo = $pdo;
        $this->ledger = $ledger;
    }
    
    /**
     * Get comprehensive dashboard metrics
     */
    public function getDashboardMetrics($period = '30_days') {
        $dateRange = $this->getDateRange($period);
        $previousRange = $this->getPreviousDateRange($period);
        
        $current = $this->getMetricsForPeriod($dateRange['start'], $dateRange['end']);
        $previous = $this->getMetricsForPeriod($previousRange['start'], $previousRange['end']);
        
        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => $this->calculateGrowth($current, $previous)
        ];
    }
    
    /**
     * Get revenue analysis with trends
     */
    public function getRevenueAnalysis($months = 12) {
        $sql = "SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions,
                    AVG(amount) as avg_transaction,
                    (SELECT COALESCE(SUM(amount), 0) FROM ledger_entries 
                     WHERE entry_type = 'debit' 
                     AND DATE_FORMAT(entry_date, '%Y-%m') = DATE_FORMAT(p.payment_date, '%Y-%m')) as expenses
                FROM payments p
                WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get project profitability analysis
     */
    public function getProjectProfitability() {
        $sql = "SELECT 
                    p.id,
                    p.service as project_name,
                    c.name as client_name,
                    p.status,
                    COALESCE(SUM(pay.amount), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END), 0) as total_expenses,
                    (COALESCE(SUM(pay.amount), 0) - COALESCE(SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END), 0)) as profit,
                    CASE 
                        WHEN COALESCE(SUM(pay.amount), 0) > 0 
                        THEN ((COALESCE(SUM(pay.amount), 0) - COALESCE(SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END), 0)) / COALESCE(SUM(pay.amount), 0)) * 100
                        ELSE 0 
                    END as profit_margin,
                    1000 as budget
                FROM projects p
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN payments pay ON p.client_id = pay.client_id
                LEFT JOIN ledger_entries le ON p.id = le.linked_project_id
                GROUP BY p.id, p.service, c.name, p.status
                ORDER BY profit DESC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get expense breakdown by category
     */
    public function getExpenseBreakdown($period = '30_days') {
        $dateRange = $this->getDateRange($period);
        
        $sql = "SELECT 
                    category,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                GROUP BY category
                ORDER BY total_amount DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get client analysis
     */
    public function getClientAnalysis() {
        $sql = "SELECT 
                    c.id,
                    c.name as client_name,
                    COUNT(DISTINCT p.id) as total_projects,
                    COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) as completed_projects,
                    COALESCE(SUM(pay.amount), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END), 0) as total_expenses,
                    (COALESCE(SUM(pay.amount), 0) - COALESCE(SUM(CASE WHEN le.entry_type = 'debit' THEN le.amount ELSE 0 END), 0)) as profit,
                    CASE 
                        WHEN COUNT(DISTINCT p.id) > 0 
                        THEN COALESCE(SUM(pay.amount), 0) / COUNT(DISTINCT p.id)
                        ELSE 0 
                    END as average_project_value,
                    CASE 
                        WHEN COUNT(DISTINCT p.id) > 0 
                        THEN (COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.id END) / COUNT(DISTINCT p.id)) * 100
                        ELSE 0 
                    END as completion_rate
                FROM clients c
                LEFT JOIN projects p ON c.id = p.client_id
                LEFT JOIN payments pay ON c.id = pay.client_id
                LEFT JOIN ledger_entries le ON p.id = le.linked_project_id
                GROUP BY c.id, c.name
                HAVING total_revenue > 0
                ORDER BY total_revenue DESC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get payment method analysis
     */
    public function getPaymentMethodAnalysis($period = '30_days') {
        $dateRange = $this->getDateRange($period);
        
        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount,
                    (SUM(amount) / (SELECT SUM(amount) FROM payments WHERE payment_date BETWEEN ? AND ?)) * 100 as percentage,
                    CASE payment_method
                        WHEN 'cash' THEN 'Cash'
                        WHEN 'card' THEN 'Card'
                        WHEN 'bank_transfer' THEN 'Bank Transfer'
                        WHEN 'check' THEN 'Check'
                        ELSE 'Other'
                    END as method_display
                FROM payments
                WHERE payment_date BETWEEN ? AND ?
                GROUP BY payment_method
                ORDER BY total_amount DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dateRange['start'], $dateRange['end'], $dateRange['start'], $dateRange['end']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cash flow analysis
     */
    public function getCashFlowAnalysis($months = 6) {
        $sql = "SELECT 
                    DATE_FORMAT(date_col, '%Y-%m') as month,
                    SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END) as cash_out,
                    (SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END)) as net_flow
                FROM (
                    SELECT payment_date as date_col, amount, 'in' as type FROM payments
                    UNION ALL
                    SELECT expense_date as date_col, amount, 'out' as type FROM expenses
                    UNION ALL
                    SELECT entry_date as date_col, amount, 
                           CASE WHEN entry_type = 'credit' THEN 'in' ELSE 'out' END as type 
                    FROM ledger_entries
                ) cash_flow
                WHERE date_col >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(date_col, '%Y-%m')
                ORDER BY month";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get financial forecasting
     */
    public function getFinancialForecasting($months = 3) {
        try {
            // Get historical data for trend analysis
            $historical = $this->getRevenueAnalysis(12);
            
            if (count($historical) < 3) {
                return ['error' => 'Insufficient historical data for forecasting'];
            }
            
            $forecast = [];
            $lastRevenue = end($historical)['revenue'];
            $lastExpenses = end($historical)['expenses'];
            
            // Simple linear trend calculation
            $revenueGrowth = $this->calculateTrendGrowth($historical, 'revenue');
            $expenseGrowth = $this->calculateTrendGrowth($historical, 'expenses');
            
            for ($i = 1; $i <= $months; $i++) {
                $forecastMonth = date('Y-m', strtotime("+$i month"));
                $forecastRevenue = $lastRevenue * (1 + ($revenueGrowth / 100));
                $forecastExpenses = $lastExpenses * (1 + ($expenseGrowth / 100));
                
                $forecast[] = [
                    'month' => $forecastMonth,
                    'forecast_revenue' => $forecastRevenue,
                    'forecast_expenses' => $forecastExpenses,
                    'forecast_profit' => $forecastRevenue - $forecastExpenses
                ];
                
                $lastRevenue = $forecastRevenue;
                $lastExpenses = $forecastExpenses;
            }
            
            return ['forecast' => $forecast];
            
        } catch (Exception $e) {
            return ['error' => 'Unable to generate forecast: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate financial insights
     */
    public function generateInsights($metrics) {
        $insights = [];
        $current = $metrics['current'];
        $growth = $metrics['growth'];
        
        // Revenue insights
        if ($growth['total_revenue'] > 10) {
            $insights[] = [
                'type' => 'positive',
                'title' => 'Strong Revenue Growth',
                'message' => "Revenue increased by {$growth['total_revenue']}% compared to the previous period."
            ];
        } elseif ($growth['total_revenue'] < -10) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Revenue Decline',
                'message' => "Revenue decreased by " . abs($growth['total_revenue']) . "%. Consider reviewing pricing and marketing strategies."
            ];
        }
        
        // Profit margin insights
        if ($current['profit_margin'] > 30) {
            $insights[] = [
                'type' => 'positive',
                'title' => 'Healthy Profit Margin',
                'message' => "Profit margin of {$current['profit_margin']}% indicates strong operational efficiency."
            ];
        } elseif ($current['profit_margin'] < 10) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Low Profit Margin',
                'message' => "Profit margin of {$current['profit_margin']}% is below optimal. Review expenses and pricing."
            ];
        }
        
        // ROI insights
        if ($current['roi'] > 20) {
            $insights[] = [
                'type' => 'positive',
                'title' => 'Excellent ROI',
                'message' => "ROI of {$current['roi']}% demonstrates strong investment returns."
            ];
        }
        
        return $insights;
    }
    
    // Helper methods
    private function getDateRange($period) {
        switch ($period) {
            case '7_days':
                return ['start' => date('Y-m-d', strtotime('-7 days')), 'end' => date('Y-m-d')];
            case '30_days':
                return ['start' => date('Y-m-d', strtotime('-30 days')), 'end' => date('Y-m-d')];
            case '90_days':
                return ['start' => date('Y-m-d', strtotime('-90 days')), 'end' => date('Y-m-d')];
            case 'current_month':
                return ['start' => date('Y-m-01'), 'end' => date('Y-m-d')];
            case 'current_year':
                return ['start' => date('Y-01-01'), 'end' => date('Y-m-d')];
            default:
                return ['start' => date('Y-m-d', strtotime('-30 days')), 'end' => date('Y-m-d')];
        }
    }
    
    private function getPreviousDateRange($period) {
        $current = $this->getDateRange($period);
        $days = (strtotime($current['end']) - strtotime($current['start'])) / 86400;
        
        return [
            'start' => date('Y-m-d', strtotime($current['start'] . " -{$days} days")),
            'end' => date('Y-m-d', strtotime($current['start'] . ' -1 day'))
        ];
    }
    
    private function getMetricsForPeriod($start, $end) {
        $revenue = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
        $revenue->execute([$start, $end]);
        $totalRevenue = $revenue->fetchColumn();
        
        $expenses = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $expenses->execute([$start, $end]);
        $totalExpenses = $expenses->fetchColumn();
        
        $investments = $this->pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM ledger_entries WHERE entry_type = 'investment' AND entry_date BETWEEN ? AND ?");
        $investments->execute([$start, $end]);
        $totalInvestments = $investments->fetchColumn();
        
        $profit = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;
        $roi = $totalInvestments > 0 ? ($profit / $totalInvestments) * 100 : 0;
        
        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'total_investments' => $totalInvestments,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
            'roi' => $roi
        ];
    }
    
    private function calculateGrowth($current, $previous) {
        $growth = [];
        foreach ($current as $key => $value) {
            if (isset($previous[$key]) && $previous[$key] != 0) {
                $growth[$key] = (($value - $previous[$key]) / $previous[$key]) * 100;
            } else {
                $growth[$key] = $value > 0 ? 100 : 0;
            }
        }
        return $growth;
    }
    
    private function calculateTrendGrowth($data, $field) {
        if (count($data) < 2) return 0;
        
        $values = array_column($data, $field);
        $n = count($values);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum($values);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $avgY = $sumY / $n;
        
        return $avgY > 0 ? ($slope / $avgY) * 100 : 0;
    }
}