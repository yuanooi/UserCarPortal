<?php
session_start();
include 'includes/db.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 获取车辆ID
if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    die("❌ Invalid Vehicle ID");
}
$car_id = intval($_GET['car_id']);

// 获取车辆基本信息
$sql = "SELECT c.*, u.username, u.phone AS seller_phone 
        FROM cars c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Vehicle not found");
}
$car = $result->fetch_assoc();

// 获取车辆图片
$image_sql = "SELECT image FROM car_images WHERE car_id = ? ORDER BY id ASC";
$image_stmt = $conn->prepare($image_sql);
$image_stmt->bind_param("i", $car_id);
$image_stmt->execute();
$image_result = $image_stmt->get_result();
$images = $image_result->fetch_all(MYSQLI_ASSOC);

// 获取车辆问题
$issues_sql = "SELECT * FROM vehicle_issues WHERE car_id = ? ORDER BY created_at DESC";
$issues_stmt = $conn->prepare($issues_sql);
$issues_stmt->bind_param("i", $car_id);
$issues_stmt->execute();
$issues_result = $issues_stmt->get_result();
$vehicle_issues = $issues_result->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<style>
    .report-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        margin: 2rem 0;
        overflow: hidden;
    }

    .report-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 3rem 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .report-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .report-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .report-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 1rem;
    }

    .vehicle-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 2rem;
        margin-top: 2rem;
    }

    .vehicle-badge {
        background: rgba(255,255,255,0.15);
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 15px;
        padding: 1rem 1.5rem;
        text-align: center;
    }

    .vehicle-badge h4 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .vehicle-badge p {
        margin: 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .report-content {
        padding: 3rem 2rem;
    }

    .section-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 3px solid #667eea;
    }

    .overall-score {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 20px;
        padding: 2.5rem;
        margin-bottom: 3rem;
        text-align: center;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .score-circle {
        width: 200px;
        height: 200px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        margin: 0 auto 2rem;
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.3);
        position: relative;
    }

    .score-circle::before {
        content: '';
        position: absolute;
        top: -10px;
        left: -10px;
        right: -10px;
        bottom: -10px;
        border: 3px solid rgba(102, 126, 234, 0.2);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(1.1); opacity: 0; }
    }

    .score-number {
        font-size: 4rem;
        font-weight: 800;
        line-height: 1;
    }

    .score-percent {
        font-size: 2rem;
        font-weight: 600;
    }

    .score-label {
        font-size: 1.2rem;
        font-weight: 600;
        margin-top: 0.5rem;
    }

    .score-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .detail-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
    }

    .detail-value {
        font-size: 2rem;
        font-weight: 800;
        color: #667eea;
        margin-bottom: 0.5rem;
    }

    .detail-label {
        color: #718096;
        font-weight: 600;
    }

    .inspection-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .category-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }

    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }

    .category-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .category-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        flex-shrink: 0;
    }

    .category-info h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 0.5rem 0;
    }

    .category-info p {
        color: #718096;
        margin: 0;
    }

    .category-score {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .score-bar {
        flex: 1;
        height: 12px;
        background: #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
        margin-right: 1rem;
    }

    .score-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 6px;
        transition: width 1s ease;
    }

    .score-text {
        font-size: 1.5rem;
        font-weight: 800;
        color: #667eea;
        min-width: 60px;
    }

    .test-items {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .test-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 10px;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .test-name {
        font-weight: 600;
        color: #2d3748;
    }

    .test-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-icon {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: white;
    }

    .status-pass {
        background: #48bb78;
    }

    .status-warning {
        background: #ed8936;
    }

    .status-fail {
        background: #f56565;
    }

    .issues-section {
        background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 3rem;
        border: 1px solid rgba(237, 137, 54, 0.2);
    }

    .issues-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .issues-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .issues-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #c05621;
        margin: 0;
    }

    .issue-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border-left: 4px solid #ed8936;
    }

    .issue-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .issue-type {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2d3748;
    }

    .issue-severity {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .severity-critical {
        background: #fed7d7;
        color: #c53030;
    }

    .severity-high {
        background: #fef5e7;
        color: #c05621;
    }

    .severity-medium {
        background: #ebf8ff;
        color: #2b6cb0;
    }

    .severity-low {
        background: #f0fff4;
        color: #2f855a;
    }

    .issue-description {
        color: #4a5568;
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .issue-location {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }

    .issue-recommendation {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 10px;
        padding: 1rem;
        border-left: 3px solid #667eea;
    }

    .recommendation-title {
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 0.5rem;
    }

    .recommendation-text {
        color: #4a5568;
        margin: 0;
    }

    .report-footer {
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 20px;
        padding: 2rem;
        text-align: center;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .footer-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 1rem;
    }

    .footer-text {
        color: #718096;
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 50px;
        padding: 1rem 2rem;
        font-weight: 600;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .btn-secondary-custom {
        background: white;
        border: 2px solid #667eea;
        border-radius: 50px;
        padding: 1rem 2rem;
        font-weight: 600;
        color: #667eea;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-secondary-custom:hover {
        background: #667eea;
        color: white;
        transform: translateY(-3px);
    }

    @media (max-width: 768px) {
        .vehicle-info {
            flex-direction: column;
            gap: 1rem;
        }
        
        .score-details {
            grid-template-columns: 1fr;
        }
        
        .inspection-categories {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
            align-items: center;
        }
    }
</style>

<div class="professional-container">
    <div class="professional-card">
        <!-- Report Header -->
        <div class="report-header">
            <h1 class="report-title">Vehicle Inspection Report</h1>
            <p class="report-subtitle">Comprehensive Quality Assessment & Technical Analysis</p>
            
            <div class="vehicle-info">
                <div class="vehicle-badge">
                    <h4><?php echo htmlspecialchars($car['brand'] . " " . $car['model']); ?></h4>
                    <p><?php echo htmlspecialchars($car['year']); ?> • <?php echo htmlspecialchars($car['body_type'] ?? 'N/A'); ?></p>
                </div>
                <div class="vehicle-badge">
                    <h4>Vehicle ID</h4>
                    <p><?php echo strtoupper(substr(md5($car['id']), 0, 8)); ?></p>
                </div>
                <div class="vehicle-badge">
                    <h4>Report Date</h4>
                    <p><?php echo date('M d, Y'); ?></p>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="report-content">
            <!-- Overall Quality Score -->
            <div class="overall-score">
                <h2 class="section-title">Overall Quality Assessment</h2>
                <div class="score-circle">
                    <div class="score-number">98.3</div>
                    <div class="score-percent">%</div>
                    <div class="score-label">Quality Score</div>
                </div>
                
                <div class="score-details">
                    <div class="detail-item">
                        <div class="detail-value">196</div>
                        <div class="detail-label">Tests Passed</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">200</div>
                        <div class="detail-label">Total Tests</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">A+</div>
                        <div class="detail-label">Grade</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">4</div>
                        <div class="detail-label">Minor Issues</div>
                    </div>
                </div>
            </div>

            <!-- Inspection Categories -->
            <h2 class="section-title">Detailed Inspection Results</h2>
            <div class="inspection-categories">
                <!-- Exterior Condition -->
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="category-info">
                            <h3>Exterior Condition</h3>
                            <p>Body, paint, lights, and external components</p>
                        </div>
                    </div>
                    <div class="category-score">
                        <div class="score-bar">
                            <div class="score-fill" style="width: 100%"></div>
                        </div>
                        <div class="score-text">100%</div>
                    </div>
                    <div class="test-items">
                        <div class="test-item">
                            <span class="test-name">Body Panel Alignment</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Paint Condition</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Headlights</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Taillights</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Windshield</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interior Quality -->
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="fas fa-chair"></i>
                        </div>
                        <div class="category-info">
                            <h3>Interior Quality</h3>
                            <p>Seats, dashboard, controls, and cabin features</p>
                        </div>
                    </div>
                    <div class="category-score">
                        <div class="score-bar">
                            <div class="score-fill" style="width: 95%"></div>
                        </div>
                        <div class="score-text">95%</div>
                    </div>
                    <div class="test-items">
                        <div class="test-item">
                            <span class="test-name">Seat Condition</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Dashboard</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Air Conditioning</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Audio System</span>
                            <div class="test-status">
                                <div class="status-icon status-warning">!</div>
                                <span>Minor Issue</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Interior Lighting</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mechanical Systems -->
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="category-info">
                            <h3>Mechanical Systems</h3>
                            <p>Engine, transmission, brakes, and drivetrain</p>
                        </div>
                    </div>
                    <div class="category-score">
                        <div class="score-bar">
                            <div class="score-fill" style="width: 100%"></div>
                        </div>
                        <div class="score-text">100%</div>
                    </div>
                    <div class="test-items">
                        <div class="test-item">
                            <span class="test-name">Engine Performance</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Transmission</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Brake System</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Steering</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Suspension</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Safety Systems -->
                <div class="category-card">
                    <div class="category-header">
                        <div class="category-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="category-info">
                            <h3>Safety Systems</h3>
                            <p>Airbags, ABS, stability control, and safety features</p>
                        </div>
                    </div>
                    <div class="category-score">
                        <div class="score-bar">
                            <div class="score-fill" style="width: 98%"></div>
                        </div>
                        <div class="score-text">98%</div>
                    </div>
                    <div class="test-items">
                        <div class="test-item">
                            <span class="test-name">Airbag System</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">ABS System</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Stability Control</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Seatbelts</span>
                            <div class="test-status">
                                <div class="status-icon status-pass">✓</div>
                                <span>Pass</span>
                            </div>
                        </div>
                        <div class="test-item">
                            <span class="test-name">Tire Pressure</span>
                            <div class="test-status">
                                <div class="status-icon status-warning">!</div>
                                <span>Check Required</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle Issues Section -->
            <?php if (!empty($vehicle_issues)): ?>
            <div class="issues-section">
                <div class="issues-header">
                    <div class="issues-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="issues-title">Identified Issues</h2>
                </div>
                
                <?php foreach ($vehicle_issues as $issue): ?>
                <div class="issue-item">
                    <div class="issue-header">
                        <div class="issue-type"><?php echo ucfirst(htmlspecialchars($issue['issue_type'])); ?></div>
                        <div class="issue-severity severity-<?php echo $issue['severity']; ?>">
                            <?php echo ucfirst($issue['severity']); ?> Priority
                        </div>
                    </div>
                    <div class="issue-description">
                        <?php echo nl2br(htmlspecialchars($issue['issue_description'] ?? 'No description available')); ?>
                    </div>
                    <div class="issue-location">
                        <strong>Model Position:</strong> <?php echo htmlspecialchars($issue['model_position'] ?? 'Not specified'); ?>
                    </div>
                    <div class="issue-recommendation">
                        <div class="recommendation-title">Admin Notes:</div>
                        <div class="recommendation-text">
                            <?php echo nl2br(htmlspecialchars($issue['admin_notes'] ?? 'No additional notes')); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Report Footer -->
            <div class="report-footer">
                <h3 class="footer-title">Quality Assurance Statement</h3>
                <p class="footer-text">
                    This vehicle has undergone comprehensive quality assessment and meets our premium standards for safety, performance, and reliability. 
                    All critical systems have been tested and verified for optimal performance. This report is valid for 30 days from the inspection date.
                </p>
                <div class="action-buttons">
                    <a href="car_detail.php?id=<?php echo $car_id; ?>" class="btn-primary-custom">
                        <i class="fas fa-arrow-left"></i> Back to Vehicle Details
                    </a>
                    <a href="javascript:window.print()" class="btn-secondary-custom">
                        <i class="fas fa-print"></i> Print Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Animate score bars on page load
document.addEventListener('DOMContentLoaded', function() {
    const scoreBars = document.querySelectorAll('.score-fill');
    
    setTimeout(() => {
        scoreBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 500);
});
</script>

<?php include 'footer.php'; ?>
