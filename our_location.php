<?php
session_start();
include 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Location - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --danger-color: #dc2626;
            --warning-color: #f59e0b;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-light: #f8fafc;
            --border-radius: 12px;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .location-header {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }

        .location-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .location-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .info-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .map-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .map-wrapper {
            width: 100%;
            height: 400px;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .contact-info {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .contact-item i {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .business-hours {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .hours-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .hours-item:last-child {
            border-bottom: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        @media (max-width: 768px) {
            .location-title {
                font-size: 2rem;
            }
            
            .location-subtitle {
                font-size: 1rem;
            }
            
            .map-wrapper {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Location Header -->
    <div class="location-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="location-title">
                        <i class="fas fa-map-marker-alt me-3"></i>Our Location
                    </h1>
                    <p class="location-subtitle">Visit us at our convenient location</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Map Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="map-container">
                    <h3 class="section-title">
                        <i class="fas fa-map me-2"></i>Find Us on the Map
                    </h3>
                    <div class="map-wrapper">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3983.8!2d101.6869!3d3.1390!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cc4c8c075b0e3b%3A0x4b4b4b4b4b4b4b4b!2sKuala%20Lumpur%2C%20Malaysia!5e0!3m2!1sen!2smy!4v1234567890123!5m2!1sen!2smy"
                            width="100%" 
                            height="100%" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Information -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="info-card">
                    <h3 class="section-title">
                        <i class="fas fa-building me-2"></i>Our Office
                    </h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h5>Address</h5>
                            <p class="text-muted">
                                123 Jalan Tun Razak<br>
                                Kuala Lumpur City Centre<br>
                                50450 Kuala Lumpur<br>
                                Malaysia
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="info-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <i class="fas fa-car"></i>
                            </div>
                            <h5>Parking</h5>
                            <p class="text-muted">
                                Free parking available<br>
                                Underground parking<br>
                                Electric vehicle charging<br>
                                Disabled access
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="contact-info">
                    <h4 class="section-title">
                        <i class="fas fa-phone me-2"></i>Contact Information
                    </h4>
                    <div class="contact-item">
                        <i class="fas fa-phone" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);"></i>
                        <div>
                            <strong>Phone</strong><br>
                            <span class="text-muted">+60 3-1234 5678</span>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope" style="background: linear-gradient(135deg, #10b981, #059669);"></i>
                        <div>
                            <strong>Email</strong><br>
                            <span class="text-muted">info@usercarportal.com</span>
                        </div>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-globe" style="background: linear-gradient(135deg, #f59e0b, #d97706);"></i>
                        <div>
                            <strong>Website</strong><br>
                            <span class="text-muted">www.usercarportal.com</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Hours -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="business-hours">
                    <h4 class="section-title">
                        <i class="fas fa-clock me-2"></i>Business Hours
                    </h4>
                    <div class="hours-item">
                        <span><strong>Monday - Friday</strong></span>
                        <span class="text-muted">9:00 AM - 6:00 PM</span>
                    </div>
                    <div class="hours-item">
                        <span><strong>Saturday</strong></span>
                        <span class="text-muted">9:00 AM - 4:00 PM</span>
                    </div>
                    <div class="hours-item">
                        <span><strong>Sunday</strong></span>
                        <span class="text-muted">Closed</span>
                    </div>
                    <div class="hours-item">
                        <span><strong>Public Holidays</strong></span>
                        <span class="text-muted">Closed</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="info-card">
                    <h4 class="section-title">
                        <i class="fas fa-directions me-2"></i>Getting Here
                    </h4>
                    <div class="mb-3">
                        <h6><i class="fas fa-train me-2"></i>By Public Transport</h6>
                        <p class="text-muted">Take the LRT to KLCC Station, then walk 5 minutes to our office.</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-car me-2"></i>By Car</h6>
                        <p class="text-muted">Free parking available. Enter from Jalan Tun Razak.</p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-bus me-2"></i>By Bus</h6>
                        <p class="text-muted">Multiple bus routes stop nearby. Check local bus schedules.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <div class="info-card">
                    <h4 class="section-title">Ready to Visit Us?</h4>
                    <p class="text-muted mb-4">We're here to help you find your perfect vehicle. Schedule a visit or contact us for more information.</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="contact.php" class="btn btn-primary">
                            <i class="fas fa-envelope me-2"></i>Contact Us
                        </a>
                        <a href="tel:+60312345678" class="btn btn-outline-primary">
                            <i class="fas fa-phone me-2"></i>Call Now
                        </a>
                        <a href="https://maps.google.com/?q=123+Jalan+Tun+Razak+Kuala+Lumpur" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-directions me-2"></i>Get Directions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
