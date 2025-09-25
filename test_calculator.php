<?php
// Test calculator functionality
session_start();
include 'includes/db.php';

// Mock car data for testing
$car = [
    'brand' => 'Toyota',
    'model' => 'Camry',
    'year' => '2020',
    'price' => 120000,
    'body_type' => 'Sedan'
];

// Vehicle-based calculation functions
function getRecommendedInterestRate($car) {
    $brand = strtolower($car['brand']);
    $year = intval($car['year']);
    $currentYear = date('Y');
    $age = $currentYear - $year;
    
    $brandRates = [
        'toyota' => 3.2,
        'honda' => 3.3,
        'proton' => 3.8,
        'perodua' => 3.9,
        'nissan' => 3.4,
        'mazda' => 3.5,
        'bmw' => 4.2,
        'mercedes' => 4.3,
        'audi' => 4.1
    ];
    
    $baseRate = $brandRates[$brand] ?? 3.8;
    
    if ($age <= 2) {
        $baseRate -= 0.2;
    } elseif ($age >= 10) {
        $baseRate += 0.5;
    }
    
    return round($baseRate, 1);
}

function getBrandRiskLevel($brand) {
    $brand = strtolower($brand);
    $riskLevels = [
        'toyota' => 'success',
        'honda' => 'success', 
        'proton' => 'warning',
        'perodua' => 'warning',
        'nissan' => 'info',
        'mazda' => 'info',
        'bmw' => 'danger',
        'mercedes' => 'danger',
        'audi' => 'danger'
    ];
    
    return $riskLevels[$brand] ?? 'info';
}

function getAgeRiskLevel($year) {
    $currentYear = date('Y');
    $age = $currentYear - intval($year);
    
    if ($age <= 3) return 'success';
    if ($age <= 7) return 'info';
    if ($age <= 12) return 'warning';
    return 'danger';
}

function getBaseInsuranceRate($car) {
    $brand = strtolower($car['brand']);
    $year = intval($car['year']);
    $currentYear = date('Y');
    $age = $currentYear - $year;
    
    $brandRates = [
        'toyota' => 2.2,
        'honda' => 2.3,
        'proton' => 2.8,
        'perodua' => 2.9,
        'nissan' => 2.4,
        'mazda' => 2.5,
        'bmw' => 3.2,
        'mercedes' => 3.3,
        'audi' => 3.1
    ];
    
    $baseRate = $brandRates[$brand] ?? 2.5;
    
    if ($age <= 2) {
        $baseRate += 0.3;
    } elseif ($age >= 10) {
        $baseRate -= 0.5;
    }
    
    return round($baseRate, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculator Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-5">Calculator Test Page</h1>
        
        <!-- Loan Calculator Test -->
        <div class="loan-calculator mt-4">
            <div class="card shadow-sm">
                <div class="card-header bg-gradient-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calculator me-3 fs-4"></i>
                        <div>
                            <h5 class="mb-0">Car Loan Calculator</h5>
                            <small class="opacity-75">Get instant financing estimates for your <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <!-- Quick Info Bar -->
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-3 bg-light">
                                <i class="fas fa-car text-primary mb-2"></i>
                                <div class="fw-bold"><?php echo htmlspecialchars($car['brand']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($car['model']); ?></small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-3 bg-light">
                                <i class="fas fa-calendar text-success mb-2"></i>
                                <div class="fw-bold"><?php echo htmlspecialchars($car['year']); ?></div>
                                <small class="text-muted">Model Year</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-3 bg-light">
                                <i class="fas fa-tag text-warning mb-2"></i>
                                <div class="fw-bold">RM <?php echo number_format($car['price'], 0); ?></div>
                                <small class="text-muted">Vehicle Price</small>
                            </div>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="border rounded p-3 bg-light">
                                <i class="fas fa-percentage text-info mb-2"></i>
                                <div class="fw-bold"><?php echo getRecommendedInterestRate($car); ?>%</div>
                                <small class="text-muted">Est. Interest Rate</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="vehiclePrice" class="form-label fw-semibold">
                                <i class="fas fa-car me-2 text-primary"></i>Vehicle Price (RM)
                            </label>
                            <input type="number" class="form-control form-control-lg" id="vehiclePrice" value="<?php echo $car['price']; ?>" readonly>
                            <div class="form-text">Fixed price for this vehicle</div>
                        </div>
                        <div class="col-md-6">
                            <label for="downPaymentPercent" class="form-label fw-semibold">
                                <i class="fas fa-hand-holding-usd me-2 text-success"></i>Down Payment
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg" id="downPaymentPercent" value="10" min="0" max="100">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Recommended: 10-20% for better rates</div>
                        </div>
                        <div class="col-md-6">
                            <label for="loanTerm" class="form-label fw-semibold">
                                <i class="fas fa-clock me-2 text-warning"></i>Loan Tenure
                            </label>
                            <select class="form-select form-select-lg" id="loanTerm">
                                <option value="3">3 Years (36 months)</option>
                                <option value="5" selected>5 Years (60 months)</option>
                                <option value="7">7 Years (84 months)</option>
                                <option value="9">9 Years (108 months)</option>
                            </select>
                            <div class="form-text">Longer tenure = lower monthly payment</div>
                        </div>
                        <div class="col-md-6">
                            <label for="interestRate" class="form-label fw-semibold">
                                <i class="fas fa-chart-line me-2 text-danger"></i>Interest Rate (% p.a.)
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg" id="interestRate" value="<?php echo getRecommendedInterestRate($car); ?>" min="0" max="20" step="0.1">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Based on your credit profile</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-primary btn-lg" onclick="calculateLoan()">
                            <i class="fas fa-calculator me-2"></i>Calculate My Monthly Payment
                        </button>
                    </div>
                    
                    <div id="loanResult" class="mt-4" style="display: none;">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check-circle me-2"></i>Your Loan Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="fs-4 fw-bold text-primary" id="monthlyPayment">RM 0</div>
                                            <div class="text-muted">Monthly Payment</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="fs-4 fw-bold text-success" id="downPaymentAmount">RM 0</div>
                                            <div class="text-muted">Down Payment</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="fs-4 fw-bold text-info" id="loanAmount">RM 0</div>
                                            <div class="text-muted">Loan Amount</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="fs-4 fw-bold text-warning" id="totalInterest">RM 0</div>
                                            <div class="text-muted">Total Interest</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Total Amount to Pay:</strong>
                                            <span id="totalAmount" class="text-primary fs-5 ms-2"></span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Effective Interest Rate:</strong>
                                            <span id="effectiveRate" class="text-success fs-5 ms-2"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Loan Calculator Functions
    function calculateLoan() {
        const vehiclePrice = parseFloat(document.getElementById('vehiclePrice').value) || 0;
        const downPaymentPercent = parseFloat(document.getElementById('downPaymentPercent').value) || 0;
        const interestRate = parseFloat(document.getElementById('interestRate').value) || 0;
        const loanTerm = parseInt(document.getElementById('loanTerm').value) || 5;
        
        // Validation
        if (vehiclePrice <= 0) {
            return; // 不显示错误，只是不计算
        }
        
        if (downPaymentPercent < 0 || downPaymentPercent > 100) {
            return; // 不显示错误，只是不计算
        }
        
        const downPaymentAmount = vehiclePrice * (downPaymentPercent / 100);
        const loanAmount = vehiclePrice - downPaymentAmount;
        const monthlyRate = interestRate / 100 / 12;
        const numberOfPayments = loanTerm * 12;
        
        // Calculate monthly payment using the loan formula
        let monthlyPayment;
        if (monthlyRate === 0) {
            monthlyPayment = loanAmount / numberOfPayments;
        } else {
            monthlyPayment = loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, numberOfPayments)) / 
                            (Math.pow(1 + monthlyRate, numberOfPayments) - 1);
        }
        
        const totalAmount = monthlyPayment * numberOfPayments;
        const totalInterest = totalAmount - loanAmount;
        const effectiveRate = ((totalInterest / loanAmount) * 100) / loanTerm;
        
        // Display results
        document.getElementById('monthlyPayment').textContent = 'RM ' + monthlyPayment.toFixed(2);
        document.getElementById('downPaymentAmount').textContent = 'RM ' + downPaymentAmount.toFixed(2);
        document.getElementById('loanAmount').textContent = 'RM ' + loanAmount.toFixed(2);
        document.getElementById('totalInterest').textContent = 'RM ' + totalInterest.toFixed(2);
        document.getElementById('totalAmount').textContent = 'RM ' + totalAmount.toFixed(2);
        document.getElementById('effectiveRate').textContent = effectiveRate.toFixed(2) + '%';
        
        document.getElementById('loanResult').style.display = 'block';
    }
    
    // Auto-calculate when inputs change - 实时自动计算
    document.addEventListener('DOMContentLoaded', function() {
        const loanInputs = ['vehiclePrice', 'downPaymentPercent', 'interestRate', 'loanTerm'];
        loanInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', function() {
                    calculateLoan(); // 输入时自动计算
                });
                element.addEventListener('change', function() {
                    calculateLoan(); // 选择框变化时自动计算
                });
            }
        });
        
        // 页面加载时自动计算一次
        setTimeout(function() {
            calculateLoan();
        }, 500);
    });
    </script>
</body>
</html>
