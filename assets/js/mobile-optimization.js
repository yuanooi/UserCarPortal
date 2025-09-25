// Mobile Optimization JavaScript
(function() {
    'use strict';

    // Check if device is mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

    // Add mobile class to body
    if (isMobile) {
        document.body.classList.add('mobile-device');
    }

    if (isTouch) {
        document.body.classList.add('touch-device');
    }

    // Mobile-specific optimizations
    function initMobileOptimizations() {
        // Prevent zoom on input focus (iOS)
        if (isMobile) {
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    if (this.style.fontSize !== '16px') {
                        this.style.fontSize = '16px';
                    }
                });
            });
        }

        // Optimize button touch areas
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            if (isMobile && !button.style.minHeight) {
                button.style.minHeight = '44px';
                button.style.minWidth = '44px';
            }
        });

        // Optimize dropdown menus for touch
        const dropdowns = document.querySelectorAll('.dropdown-toggle');
        dropdowns.forEach(dropdown => {
            dropdown.addEventListener('touchstart', function(e) {
                // Prevent double-tap zoom
                e.preventDefault();
            });
        });

        // Optimize carousel for touch
        const carousels = document.querySelectorAll('.carousel');
        carousels.forEach(carousel => {
            let startX = 0;
            let startY = 0;
            let endX = 0;
            let endY = 0;

            carousel.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            });

            carousel.addEventListener('touchend', function(e) {
                endX = e.changedTouches[0].clientX;
                endY = e.changedTouches[0].clientY;
                
                const diffX = startX - endX;
                const diffY = startY - endY;
                
                // Only trigger if horizontal swipe is more significant than vertical
                if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                    const nextBtn = carousel.querySelector('.carousel-control-next');
                    const prevBtn = carousel.querySelector('.carousel-control-prev');
                    
                    if (diffX > 0 && nextBtn) {
                        nextBtn.click();
                    } else if (diffX < 0 && prevBtn) {
                        prevBtn.click();
                    }
                }
            });
        });

        // Optimize modals for mobile
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                if (isMobile) {
                    // Prevent body scroll when modal is open
                    document.body.style.overflow = 'hidden';
                }
            });

            modal.addEventListener('hidden.bs.modal', function() {
                if (isMobile) {
                    // Restore body scroll when modal is closed
                    document.body.style.overflow = '';
                }
            });
        });

        // Optimize image galleries for touch
        const galleries = document.querySelectorAll('.image-gallery, .car-image-gallery');
        galleries.forEach(gallery => {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;

            gallery.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            });

            gallery.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                
                currentX = e.touches[0].clientX;
                const diffX = startX - currentX;
                
                // Add visual feedback for swipe
                gallery.style.transform = `translateX(${-diffX * 0.1}px)`;
            });

            gallery.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                
                isDragging = false;
                gallery.style.transform = '';
                
                const diffX = startX - currentX;
                
                if (Math.abs(diffX) > 100) {
                    const images = gallery.querySelectorAll('img');
                    const currentIndex = Array.from(images).findIndex(img => img.classList.contains('active'));
                    
                    if (diffX > 0 && currentIndex < images.length - 1) {
                        // Swipe left - next image
                        images[currentIndex].classList.remove('active');
                        images[currentIndex + 1].classList.add('active');
                    } else if (diffX < 0 && currentIndex > 0) {
                        // Swipe right - previous image
                        images[currentIndex].classList.remove('active');
                        images[currentIndex - 1].classList.add('active');
                    }
                }
            });
        });

        // Optimize forms for mobile
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (isMobile) {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    }
                }
            });
        });

        // Optimize navigation for mobile
        const navbarToggler = document.querySelector('.navbar-toggler');
        if (navbarToggler && isMobile) {
            navbarToggler.addEventListener('click', function() {
                const navbarCollapse = document.querySelector('.navbar-collapse');
                if (navbarCollapse) {
                    // Add smooth animation
                    navbarCollapse.style.transition = 'all 0.3s ease';
                }
            });
        }

        // Optimize tables for mobile
        const tables = document.querySelectorAll('.table-responsive table');
        tables.forEach(table => {
            if (isMobile) {
                // Add horizontal scroll indicator
                const wrapper = table.closest('.table-responsive');
                if (wrapper) {
                    wrapper.style.position = 'relative';
                    
                    // Add scroll indicators
                    const leftIndicator = document.createElement('div');
                    leftIndicator.className = 'scroll-indicator scroll-indicator-left';
                    leftIndicator.innerHTML = '<i class="fas fa-chevron-left"></i>';
                    
                    const rightIndicator = document.createElement('div');
                    rightIndicator.className = 'scroll-indicator scroll-indicator-right';
                    rightIndicator.innerHTML = '<i class="fas fa-chevron-right"></i>';
                    
                    wrapper.appendChild(leftIndicator);
                    wrapper.appendChild(rightIndicator);
                    
                    // Update indicators on scroll
                    wrapper.addEventListener('scroll', function() {
                        const scrollLeft = this.scrollLeft;
                        const maxScroll = this.scrollWidth - this.clientWidth;
                        
                        leftIndicator.style.opacity = scrollLeft > 0 ? '1' : '0.3';
                        rightIndicator.style.opacity = scrollLeft < maxScroll ? '1' : '0.3';
                    });
                }
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileOptimizations);
    } else {
        initMobileOptimizations();
    }

    // Add CSS for mobile optimizations
    const style = document.createElement('style');
    style.textContent = `
        .mobile-device .btn {
            min-height: 44px;
            min-width: 44px;
        }
        
        .touch-device .btn:hover {
            transform: none;
        }
        
        .scroll-indicator {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            transition: opacity 0.3s ease;
        }
        
        .scroll-indicator-left {
            left: 10px;
        }
        
        .scroll-indicator-right {
            right: 10px;
        }
        
        .mobile-device .modal-dialog {
            margin: 0.5rem;
            max-width: calc(100% - 1rem);
        }
        
        .mobile-device .carousel-control-prev,
        .mobile-device .carousel-control-next {
            width: 50px;
            height: 50px;
        }
        
        .mobile-device .navbar-collapse {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .mobile-device .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            
            .mobile-device .card {
                margin-bottom: 1rem;
            }
            
            .mobile-device .btn-group-vertical .btn {
                margin-bottom: 0.5rem;
            }
            
            .mobile-device .input-group {
                flex-direction: column;
            }
            
            .mobile-device .input-group .form-control {
                border-radius: 0.375rem !important;
                margin-bottom: 0.5rem;
            }
            
            .mobile-device .input-group .btn {
                border-radius: 0.375rem !important;
            }
        }
    `;
    document.head.appendChild(style);

})();
