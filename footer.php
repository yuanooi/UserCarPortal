<footer class="bg-dark text-white text-center py-3 mt-4">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> Used Car Portal. All rights reserved.</p>
        <ul class="list-inline">
            <li class="list-inline-item"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
            <li class="list-inline-item"><a href="login.php" class="text-white text-decoration-none">Login</a></li>
            <li class="list-inline-item"><a href="register.php" class="text-white text-decoration-none">Register</a></li>
            <li class="list-inline-item"><a href="our_location.php" class="text-white text-decoration-none">Our Location</a></li>
        </ul>
        <ul class="list-inline social-links mt-2">
            <li class="list-inline-item">
                <a href="#" class="text-white" title="Follow us on Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
            </li>
            <li class="list-inline-item">
                <a href="#" class="text-white" title="Follow us on Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
            </li>
        </ul>
    </div>
    <style>
        :root {
            --primary-color: #2563eb;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .social-links .list-inline-item a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transition: var(--transition);
        }

        .social-links .list-inline-item a:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .social-links .list-inline-item i {
            font-size: 1.2rem;
        }

        @media (max-width: 576px) {
            .social-links .list-inline-item a {
                width: 32px;
                height: 32px;
            }
            .social-links .list-inline-item i {
                font-size: 1rem;
            }
        }
    </style>
</footer>