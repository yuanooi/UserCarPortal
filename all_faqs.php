<?php
session_start();
include 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Car Portal - Frequently Asked Questions">
    <title>Frequently Asked Questions - User Car Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #10b981;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --header-height: 70px; /* Height of main header */
            --faq-nav-height: 60px; /* Height of FAQ nav */
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            color: #1e293b;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--card-shadow);
            height: var(--header-height);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }

        .nav-link {
            color: var(--secondary-color) !important;
            font-weight: 500;
            transition: var(--transition);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
        }

        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: rgba(37, 99, 235, 0.1);
        }

        /* FAQ Section */
        .faq-section {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .faq-nav {
            position: sticky;
            top: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            z-index: 900;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 0.5rem;
            height: var(--faq-nav-height);
        }

        .faq-nav .nav-btn {
            background: #f8fafc;
            color: var(--secondary-color);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            border: 2px solid #e2e8f0;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            text-align: center;
            justify-content: center;
        }

        .faq-nav .nav-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .faq-nav .nav-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .faq-section .accordion-button {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--secondary-color);
            padding: 1.25rem;
        }

        .faq-section .accordion-button:not(.collapsed) {
            background: var(--primary-color);
            color: white;
        }

        .faq-section .accordion-button:focus {
            box-shadow: none;
            border: none;
        }

        .faq-section .accordion-body {
            background: #f8fafc;
            color: #1e293b;
            font-size: 1rem;
            line-height: 1.7;
        }

        .faq-section .accordion-item {
            border: none;
            margin-bottom: 1rem;
            border-radius: 8px;
            overflow: hidden;
        }

        .faq-section .category-header {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
        }

        .faq-section .category-header i {
            margin-right: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .faq-section {
                margin: 1rem;
                padding: 1.5rem;
            }
            .faq-nav {
                top: var(--header-height);
                flex-direction: column;
                align-items: stretch;
                height: auto;
                padding: 0.5rem;
            }
            .faq-nav .nav-btn {
                width: 100%;
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            .faq-section .category-header {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .faq-section .accordion-button {
                font-size: 0.95rem;
                padding: 1rem;
            }
            .faq-section .accordion-body {
                font-size: 0.9rem;
            }
            .faq-section .category-header {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<?php include 'header.php'; ?>

<!-- FAQ Section -->
<section class="faq-section">
    <h2 class="text-center mb-4" style="color: var(--primary-color);">All Frequently Asked Questions</h2>
    <div class="faq-nav">
        <a href="#selling-car" class="nav-btn active" data-section="selling-car"><i class="fas fa-car"></i> Selling Your Car</a>
        <a href="#buying-car" class="nav-btn" data-section="buying-car"><i class="fas fa-car"></i> Buying a Car</a>
        <a href="#car-loans" class="nav-btn" data-section="car-loans"><i class="fas fa-money-check-alt"></i> Car Loans</a>
        <a href="#car-insurance" class="nav-btn" data-section="car-insurance"><i class="fas fa-shield-alt"></i> Car Insurance</a>
    </div>

    <!-- Selling Car FAQs -->
    <div id="selling-car">
        <h3 class="category-header"><i class="fas fa-car me-2"></i>Selling Your Car</h3>
        <div class="accordion" id="faqSellingAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqSelling1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseSelling1" aria-expanded="false" aria-controls="faqCollapseSelling1">
                        <i class="fas fa-question-circle me-2"></i> How do I list my car for sale on the platform?
                    </button>
                </h2>
                <div id="faqCollapseSelling1" class="accordion-collapse collapse" aria-labelledby="faqSelling1" data-bs-parent="#faqSellingAccordion">
                    <div class="accordion-body">
                        Log in as a user, navigate to the "Upload Vehicle" page, and complete the vehicle details form with photos. Your listing is reviewed and approved by our team within 24 hours.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqSelling2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseSelling2" aria-expanded="false" aria-controls="faqCollapseSelling2">
                        <i class="fas fa-question-circle me-2"></i> Is there a fee to sell my car?
                    </button>
                </h2>
                <div id="faqCollapseSelling2" class="accordion-collapse collapse" aria-labelledby="faqSelling2" data-bs-parent="#faqSellingAccordion">
                    <div class="accordion-body">
                        Basic listings are free. Optional premium listings for better visibility may incur a small fee, detailed on the “Sell Your Car” page.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqSelling3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseSelling3" aria-expanded="false" aria-controls="faqCollapseSelling3">
                        <i class="fas fa-question-circle me-2"></i> What documents are needed to sell my car?
                    </button>
                </h2>
                <div id="faqCollapseSelling3" class="accordion-collapse collapse" aria-labelledby="faqSelling3" data-bs-parent="#faqSellingAccordion">
                    <div class="accordion-body">
                        Provide vehicle registration, proof of ownership, and a valid ID. Ensure all details match your listing to expedite approval.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqSelling4">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseSelling4" aria-expanded="false" aria-controls="faqCollapseSelling4">
                        <i class="fas fa-question-circle me-2"></i> Can I sell a car with an outstanding loan?
                    </button>
                </h2>
                <div id="faqCollapseSelling4" class="accordion-collapse collapse" aria-labelledby="faqSelling4" data-bs-parent="#faqSellingAccordion">
                    <div class="accordion-body">
                        Yes, but you must settle the loan or coordinate with your lender. Contact our support team via WhatsApp for guidance.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Buying Car FAQs -->
    <div id="buying-car">
        <h3 class="category-header"><i class="fas fa-car me-2"></i>Buying a Car</h3>
        <div class="accordion" id="faqBuyingAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqBuying1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseBuying1" aria-expanded="false" aria-controls="faqCollapseBuying1">
                        <i class="fas fa-question-circle me-2"></i> How do I know a car’s condition is reliable?
                    </button>
                </h2>
                <div id="faqCollapseBuying1" class="accordion-collapse collapse" aria-labelledby="faqBuying1" data-bs-parent="#faqBuyingAccordion">
                    <div class="accordion-body">
                        All cars undergo a basic inspection before listing. You can arrange a detailed inspection or test drive with the user via WhatsApp to verify the condition.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqBuying2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseBuying2" aria-expanded="false" aria-controls="faqCollapseBuying2">
                        <i class="fas fa-question-circle me-2"></i> Can I book a test drive?
                    </button>
                </h2>
                <div id="faqCollapseBuying2" class="accordion-collapse collapse" aria-labelledby="faqBuying2" data-bs-parent="#faqBuyingAccordion">
                    <div class="accordion-body">
                        Yes, contact the user via WhatsApp to schedule a test drive at a convenient time and location.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqBuying3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseBuying3" aria-expanded="false" aria-controls="faqCollapseBuying3">
                        <i class="fas fa-question-circle me-2"></i> What if I want to cancel my car booking?
                    </button>
                </h2>
                <div id="faqCollapseBuying3" class="accordion-collapse collapse" aria-labelledby="faqBuying3" data-bs-parent="#faqBuyingAccordion">
                    <div class="accordion-body">
                        If you paid a booking fee, contact the user within 24 hours to discuss refund options. Refunds depend on the user's terms.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqBuying4">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseBuying4" aria-expanded="false" aria-controls="faqCollapseBuying4">
                        <i class="fas fa-question-circle me-2"></i> Are prices negotiable?
                    </button>
                </h2>
                <div id="faqCollapseBuying4" class="accordion-collapse collapse" aria-labelledby="faqBuying4" data-bs-parent="#faqBuyingAccordion">
                    <div class="accordion-body">
                        Prices are set by users but can often be negotiated. Discuss directly with the user via WhatsApp to agree on terms.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Car Loans FAQs -->
    <div id="car-loans">
        <h3 class="category-header"><i class="fas fa-money-check-alt me-2"></i>Car Loans</h3>
        <div class="accordion" id="faqLoanAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqLoan1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseLoan1" aria-expanded="false" aria-controls="faqCollapseLoan1">
                        <i class="fas fa-question-circle me-2"></i> How are monthly loan payments estimated?
                    </button>
                </h2>
                <div id="faqCollapseLoan1" class="accordion-collapse collapse" aria-labelledby="faqLoan1" data-bs-parent="#faqLoanAccordion">
                    <div class="accordion-body">
                        Estimates use a 3% annual interest rate over 5 years unless specified by the user. Contact the user or a bank for exact terms.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqLoan2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseLoan2" aria-expanded="false" aria-controls="faqCollapseLoan2">
                        <i class="fas fa-question-circle me-2"></i> Can I apply for a loan through the platform?
                    </button>
                </h2>
                <div id="faqCollapseLoan2" class="accordion-collapse collapse" aria-labelledby="faqLoan2" data-bs-parent="#faqLoanAccordion">
                    <div class="accordion-body">
                        We provide loan estimates but do not process loans directly. Coordinate with the user or a bank to apply for financing.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqLoan3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseLoan3" aria-expanded="false" aria-controls="faqCollapseLoan3">
                        <i class="fas fa-question-circle me-2"></i> What affects my loan eligibility?
                    </button>
                </h2>
                <div id="faqCollapseLoan3" class="accordion-collapse collapse" aria-labelledby="faqLoan3" data-bs-parent="#faqLoanAccordion">
                    <div class="accordion-body">
                        Loan eligibility depends on your credit score, income, and the car’s price. Consult with your bank for specific requirements.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqLoan4">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseLoan4" aria-expanded="false" aria-controls="faqCollapseLoan4">
                        <i class="fas fa-question-circle me-2"></i> Can I get a loan for any listed car?
                    </button>
                </h2>
                <div id="faqCollapseLoan4" class="accordion-collapse collapse" aria-labelledby="faqLoan4" data-bs-parent="#faqLoanAccordion">
                    <div class="accordion-body">
                        Most listed cars are eligible for loans, but terms vary by user and lender. Verify with the user or your bank.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Car Insurance FAQs -->
    <div id="car-insurance">
        <h3 class="category-header"><i class="fas fa-shield-alt me-2"></i>Car Insurance</h3>
        <div class="accordion" id="faqInsuranceAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqInsurance1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseInsurance1" aria-expanded="false" aria-controls="faqCollapseInsurance1">
                        <i class="fas fa-question-circle me-2"></i> Do I need insurance before driving my car?
                    </button>
                </h2>
                <div id="faqCollapseInsurance1" class="accordion-collapse collapse" aria-labelledby="faqInsurance1" data-bs-parent="#faqInsuranceAccordion">
                    <div class="accordion-body">
                        Yes, insurance is required before driving. Arrange coverage with an insurer after finalizing the purchase.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqInsurance2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseInsurance2" aria-expanded="false" aria-controls="faqCollapseInsurance2">
                        <i class="fas fa-question-circle me-2"></i> How are insurance costs calculated?
                    </button>
                </h2>
                <div id="faqCollapseInsurance2" class="accordion-collapse collapse" aria-labelledby="faqInsurance2" data-bs-parent="#faqInsuranceAccordion">
                    <div class="accordion-body">
                        Insurance costs are provided by the user or estimated at 5% of the car's price per year. Contact an insurer for a precise quote.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqInsurance3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseInsurance3" aria-expanded="false" aria-controls="faqCollapseInsurance3">
                        <i class="fas fa-question-circle me-2"></i> Can I transfer my existing insurance to a new car?
                    </button>
                </h2>
                <div id="faqCollapseInsurance3" class="accordion-collapse collapse" aria-labelledby="faqInsurance3" data-bs-parent="#faqInsuranceAccordion">
                    <div class="accordion-body">
                        Most insurers allow transferring existing insurance to a new car. Contact your provider to update the policy details.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqInsurance4">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseInsurance4" aria-expanded="false" aria-controls="faqCollapseInsurance4">
                        <i class="fas fa-question-circle me-2"></i> What insurance types are recommended?
                    </button>
                </h2>
                <div id="faqCollapseInsurance4" class="accordion-collapse collapse" aria-labelledby="faqInsurance4" data-bs-parent="#faqInsuranceAccordion">
                    <div class="accordion-body">
                        Comprehensive insurance is recommended for full coverage, including accidents, theft, and damages. Consult your insurer for options.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing tooltips and FAQ navigation...');

        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // FAQ navigation
        const navButtons = document.querySelectorAll('.faq-nav .nav-btn');
        const headerHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height'));
        const faqNavHeight = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--faq-nav-height'));

        navButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const sectionId = this.getAttribute('data-section');
                const section = document.getElementById(sectionId);
                if (section) {
                    const offsetTop = section.getBoundingClientRect().top + window.scrollY - (headerHeight + faqNavHeight + 20);
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                    console.log(`Scrolling to section: ${sectionId}, offset: ${offsetTop}`);
                    // Update active class
                    navButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                } else {
                    console.error(`Section not found: ${sectionId}`);
                }
            });
        });

        // Intersection Observer for active nav button
        const sections = document.querySelectorAll('.faq-section > div[id]');
        const observerOptions = {
            root: null,
            rootMargin: `-${headerHeight + faqNavHeight + 20}px 0px -50% 0px`,
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.getAttribute('id');
                    navButtons.forEach(button => {
                        button.classList.remove('active');
                        if (button.getAttribute('data-section') === sectionId) {
                            button.classList.add('active');
                            console.log(`Active section: ${sectionId}`);
                        }
                    });
                }
            });
        }, observerOptions);

        sections.forEach(section => observer.observe(section));
    });
</script>
</body>
</html>