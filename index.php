<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediMind - AI-Powered Healthcare Platform</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #e0e7ff;
            --secondary: #3f37c9;
            --accent: #7209b7;
            --accent-light: #b5179e;
            --dark: #14213d;
            --dark-light: #1f2937;
            --light: #f8fafc;
            --gray: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08);
            position: fixed;
            width: 100%;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.8);
        }

        header.scrolled {
            background-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 5px 0;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            transition: all 0.3s;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            font-family: 'Montserrat', sans-serif;
            letter-spacing: -0.5px;
        }

        .logo i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 28px;
            transition: transform 0.5s ease;
        }

        .logo:hover i {
            transform: rotate(360deg);
        }

        .nav-links {
            display: flex;
            list-style: none;
        }

        .nav-links li {
            margin-left: 30px;
            position: relative;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            padding: 5px 0;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            bottom: 0;
            left: 0;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 3px;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            z-index: 1;
            transform: translateY(0);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
            transform-origin: left center;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .btn:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-primary::before {
            background: linear-gradient(135deg, var(--primary-dark), var(--accent-light));
            transform: scaleX(0);
        }

        .btn-primary:hover::before {
            transform: scaleX(1);
        }

        /* Improved Outline Button */
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-outline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            z-index: -1;
            transform: scaleX(0);
            transform-origin: right center;
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .btn-outline:hover {
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.25);
        }

        .btn-outline:hover::before {
            transform: scaleX(1);
            transform-origin: left center;
        }

        /* Hero Section */
        .hero {
            padding: 180px 0 100px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #f0f4ff 0%, #f9f0ff 100%);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(67, 97, 238, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            z-index: 0;
            animation: pulse 8s infinite alternate;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.1);
                opacity: 0.4;
            }
        }

        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            flex: 1;
            max-width: 600px;
        }

        .hero-image {
            flex: 1;
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-20px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            display: block;
            filter: drop-shadow(0 20px 30px rgba(67, 97, 238, 0.2));
        }

        .hero h1 {
            font-size: 52px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
            color: var(--dark);
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: gradient 8s ease infinite;
            background-size: 200% 200%;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .hero p {
            font-size: 18px;
            margin-bottom: 30px;
            color: var(--dark-light);
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            z-index: 0;
            filter: blur(40px);
            opacity: 0.6;
        }

        .circle-1 {
            width: 400px;
            height: 400px;
            top: -200px;
            left: -200px;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        .circle-2 {
            width: 300px;
            height: 300px;
            bottom: 50px;
            right: 100px;
            background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
            animation: rotate 20s linear infinite reverse;
        }

        .circle-3 {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 30%;
            background: radial-gradient(circle, var(--secondary) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite alternate;
        }

        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Features Section */
        .features {
            padding: 100px 0;
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 42px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 15px;
            font-family: 'Montserrat', sans-serif;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .section-title p {
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
            font-size: 18px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background-color: white;
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            z-index: 2;
        }

        .feature-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: linear-gradient(135deg, var(--primary-light), #f5f0ff);
            z-index: -1;
            transition: height 0.4s ease;
        }

        .feature-card:hover::after {
            height: 100%;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotateY(180deg);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4);
        }

        .feature-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 700;
        }

        .feature-card p {
            color: var(--gray);
        }

        /* How It Works */
        .how-it-works {
            padding: 100px 0;
            background-color: #f8fafc;
            position: relative;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: 50px;
        }

        .steps::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            z-index: 1;
            border-radius: 2px;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            width: 25%;
        }

        .step-number {
            width: 50px;
            height: 50px;
            background-color: white;
            border: 4px solid var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary);
            margin: 0 auto 20px;
            position: relative;
            font-size: 20px;
            transition: all 0.4s ease;
        }

        .step:hover .step-number {
            background-color: var(--primary);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }

        .step h3 {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 600;
        }

        .step p {
            color: var(--gray);
            padding: 0 15px;
        }

        /* Testimonials */
        .testimonials {
            padding: 100px 0;
            background-color: white;
            position: relative;
            overflow: hidden;
        }

        .testimonials::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(67, 97, 238, 0.03)" d="M0,0 L100,0 L100,100 L0,100 Z" /></svg>');
            background-size: 30px 30px;
            opacity: 0.5;
            z-index: 0;
        }

        .testimonial-slider {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            padding: 0 20px;
        }

        .testimonial-track {
            display: flex;
            transition: transform 0.5s ease;
        }

        .testimonial {
            background-color: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin: 20px;
            text-align: center;
            transition: all 0.4s ease;
            min-width: 100%;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .testimonial:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
        }

        .testimonial-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid var(--primary-light);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
            transition: all 0.4s ease;
        }

        .testimonial:hover .testimonial-avatar {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .testimonial-content {
            margin-bottom: 20px;
            font-style: italic;
            color: var(--dark-light);
            position: relative;
            font-size: 18px;
            line-height: 1.8;
        }

        .testimonial-content::before,
        .testimonial-content::after {
            content: '"';
            font-size: 60px;
            color: var(--primary);
            opacity: 0.2;
            position: absolute;
            font-family: serif;
            line-height: 1;
        }

        .testimonial-content::before {
            top: -20px;
            left: -15px;
        }

        .testimonial-content::after {
            bottom: -50px;
            right: -15px;
        }

        .testimonial-author {
            font-weight: 700;
            color: var(--dark);
            margin-top: 30px;
            font-size: 18px;
        }

        .testimonial-role {
            color: var(--primary);
            font-size: 15px;
            margin-top: 5px;
            font-weight: 500;
        }

        .slider-nav {
            display: flex;
            justify-content: center;
            margin-top: 40px;
        }

        .slider-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--gray);
            margin: 0 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slider-dot.active {
            background-color: var(--primary);
            transform: scale(1.2);
        }

        /* CTA Section */
        .cta {
            padding: 120px 0;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(255,255,255,0.05)" d="M0,0 L100,0 L100,100 L0,100 Z" /></svg>');
            background-size: 30px 30px;
            opacity: 0.3;
        }

        .cta h2 {
            font-size: 42px;
            margin-bottom: 20px;
            position: relative;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
        }

        .cta p {
            max-width: 700px;
            margin: 0 auto 40px;
            opacity: 0.9;
            position: relative;
            font-size: 18px;
        }

        .cta .btn {
            position: relative;
            background-color: white;
            color: var(--primary);
            font-weight: 600;
            padding: 15px 40px;
            font-size: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .cta .btn:hover {
            background-color: rgba(255, 255, 255, 0.95);
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .cta-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: float-up linear infinite;
        }

        @keyframes float-up {
            0% {
                transform: translateY(100vh) translateX(0);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(-100px) translateX(100px);
                opacity: 0;
            }
        }

        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-logo {
            font-size: 26px;
            font-weight: 800;
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-family: 'Montserrat', sans-serif;
        }

        .footer-logo i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 28px;
        }

        .footer-about p {
            color: #94a3b8;
            margin-bottom: 20px;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
            font-size: 18px;
        }

        .social-links a:hover {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }

        .footer-links h3 {
            font-size: 20px;
            margin-bottom: 25px;
            color: white;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }

        .footer-links h3::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-links a:hover {
            color: var(--primary);
            transform: translateX(5px);
        }

        .footer-contact p {
            color: #94a3b8;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .footer-contact i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 18px;
            width: 20px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 14px;
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .slide-in-left.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .slide-in-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .scale-in {
            opacity: 0;
            transform: scale(0.8);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .scale-in.visible {
            opacity: 1;
            transform: scale(1);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .hero-text {
                margin-bottom: 50px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .steps {
                flex-direction: column;
                align-items: center;
            }

            .steps::before {
                display: none;
            }

            .step {
                width: 100%;
                margin-bottom: 40px;
            }

            .step-number {
                margin-bottom: 15px;
            }

            .hero h1 {
                font-size: 42px;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 36px;
            }

            .section-title h2 {
                font-size: 32px;
            }

            .nav-links {
                display: none;
            }

            .hero-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

        /* Hero Image Visual Improvements */
        .hero-image {
            flex: 1;
            position: relative;
            padding-left: 50px;
        }

        .hero-visual {
            position: relative;
            width: 100%;
            max-width: 500px;
            height: 500px;
            margin: 0 auto;
        }

        .medical-icon {
            position: absolute;
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.15);
            z-index: 2;
            animation: float-icon 6s ease-in-out infinite;
            animation-delay: var(--delay);
            transform: translate(var(--x), var(--y));
        }

        @keyframes float-icon {

            0%,
            100% {
                transform: translate(var(--x), var(--y)) rotate(0deg);
            }

            50% {
                transform: translate(calc(var(--x) + 10px), calc(var(--y) + 20px)) rotate(5deg);
            }
        }

        .phone-mockup {
            position: absolute;
            width: 280px;
            height: 500px;
            background: white;
            border-radius: 40px;
            padding: 15px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            overflow: hidden;
        }

        .phone-screen {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f0f4ff 0%, #f9f0ff 100%);
            border-radius: 30px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .health-stats {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 15px;
        }

        .stat-item {
            width: calc(50% - 10px);
            background: white;
            border-radius: 15px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .stat-item.active {
            opacity: 1;
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.15);
            border: 2px solid var(--primary-light);
        }

        .stat-item i {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 5px;
            display: block;
        }

        .stat-item span {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        .ai-message {
            margin-top: 20px;
        }

        .message-bubble {
            background: white;
            border-radius: 20px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            margin-bottom: 15px;
            max-width: 80%;
        }

        .message-bubble::after {
            content: '';
            position: absolute;
            bottom: -10px;
            right: 20px;
            width: 0;
            height: 0;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-top: 10px solid white;
        }

        .ai-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            margin-left: auto;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        /* Animation for health stats */
        @keyframes stat-rotate {

            0%,
            25% {
                opacity: 1;
                transform: scale(1.05);
            }

            25.1%,
            100% {
                opacity: 0.6;
                transform: scale(1);
            }
        }

        .stat-item:nth-child(1) {
            animation: stat-rotate 8s infinite;
        }

        .stat-item:nth-child(2) {
            animation: stat-rotate 8s infinite 2s;
        }

        .stat-item:nth-child(3) {
            animation: stat-rotate 8s infinite 4s;
        }

        .stat-item:nth-child(4) {
            animation: stat-rotate 8s infinite 6s;
        }

        /* Improved Hero Layout */
        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }

        .hero-text {
            flex: 1;
            max-width: 550px;
            padding-right: 20px;
        }

        .hero-image {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        /* App Mockup Styles */
        .app-mockup {
            position: relative;
            width: 100%;
            max-width: 350px;
            margin: 0 auto;
        }

        .app-screen {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(67, 97, 238, 0.15);
            position: relative;
            z-index: 2;
        }

        .app-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .app-avatar {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .app-info {
            flex: 1;
        }

        .app-title {
            font-weight: 600;
            font-size: 16px;
        }

        .app-subtitle {
            font-size: 13px;
            opacity: 0.8;
        }

        .app-message {
            padding: 20px;
            background: #f9f9ff;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-bubble {
            padding: 12px 15px;
            border-radius: 18px;
            max-width: 80%;
            font-size: 10px;
            line-height: 1.4;
        }

        .message-bubble.ai {
            background: white;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
        }

        .message-bubble.user {
            background: orchid;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .app-stats {
            display: flex;
            padding: 15px;
            background: white;
            justify-content: space-around;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card {
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .stat-card.active {
            background: var(--primary-light);
            opacity: 1;
            transform: scale(1.1);
        }

        .stat-card i {
            display: block;
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-card span {
            font-size: 12px;
            font-weight: 600;
        }

        /* Floating Icons */
        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .floating-icon {
            position: absolute;
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.15);
            animation: float 6s ease-in-out infinite;
            animation-delay: var(--delay);
            transform: translate(var(--x), var(--y));
        }

        /* Hero Stats */
        .hero-stats {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
        }

        .stat-label {
            font-size: 14px;
            color: var(--gray);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .hero-text {
                padding-right: 0;
                margin-bottom: 50px;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .hero-stats {
                flex-direction: column;
                gap: 15px;
            }

            .app-mockup {
                max-width: 240px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header id="header">
        <div class="container">
            <nav>
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>MediMind</span>
                </div>
                <ul class="nav-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
                <div class="auth-buttons">
                    <a href="includes/auth.php?login=1" class="btn btn-outline">Login</a>
                    <a href="includes/auth.php?register=1" class="btn btn-primary">Register</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        <div class="circle circle-3"></div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-text slide-in-left">
                    <h1>AI-Powered Healthcare <span>For Everyone</span></h1>
                    <p>MediMind combines advanced artificial intelligence with expert medical professionals to deliver
                        personalized healthcare solutions anytime, anywhere.</p>
                    <div class="hero-buttons">
                        <a href="includes/auth.php?register=1" class="btn btn-primary">Get Started Free</a>
                        <a href="#how-it-works" class="btn btn-outline">How It Works</a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-value">95%</div>
                            <div class="stat-label">Diagnosis Accuracy</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">24/7</div>
                            <div class="stat-label">Doctor Access</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">5min</div>
                            <div class="stat-label">Avg. Wait Time</div>
                        </div>
                    </div>
                </div>
                <div class="hero-image slide-in-right">
                    <div class="app-mockup">
                        <div class="app-screen">
                            <div class="app-header">
                                <div class="app-avatar">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div class="app-info">
                                    <div class="app-title">Dr. Sarah Miller</div>
                                    <div class="app-subtitle">Cardiologist</div>
                                </div>
                            </div>
                            <div class="app-message">
                                <div class="message-bubble ai">
                                    <p>Based on your symptoms, I recommend a consultation about possible allergies.</p>
                                </div>
                                <div class="message-bubble user">
                                    <p>I've been having headaches and sneezing frequently</p>
                                </div>
                            </div>
                            <div class="app-stats">
                                <div class="stat-card">
                                    <i class="fas fa-heart"></i>
                                    <span>72 BPM</span>
                                </div>
                                <div class="stat-card active">
                                    <i class="fas fa-thermometer"></i>
                                    <span>98.6°F</span>
                                </div>
                                <div class="stat-card">
                                    <i class="fas fa-lungs"></i>
                                    <span>98% O₂</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="floating-icons">
                        <div class="floating-icon" style="--delay: 0s; --x: -20px; --y: -30px;">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <div class="floating-icon" style="--delay: 0.5s; --x: 30px; --y: -10px;">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="floating-icon" style="--delay: 1s; --x: -10px; --y: 20px;">
                            <i class="fas fa-stethoscope"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title fade-in">
                <h2>Our Powerful Features</h2>
                <p>Discover how MediMind revolutionizes healthcare with cutting-edge technology and personalized care
                </p>
            </div>
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>AI Symptom Analysis</h3>
                    <p>Our advanced neural network evaluates your symptoms with 95% accuracy to suggest possible
                        conditions.</p>
                </div>
                <div class="feature-card fade-in" style="transition-delay: 0.1s">
                    <div class="feature-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <h3>Virtual Consultations</h3>
                    <p>HD video calls with board-certified doctors from the comfort of your home.</p>
                </div>
                <div class="feature-card fade-in" style="transition-delay: 0.2s">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Smart Scheduling</h3>
                    <p>AI-powered appointment booking that matches your schedule with doctor availability.</p>
                </div>
                <div class="feature-card fade-in" style="transition-delay: 0.3s">
                    <div class="feature-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h3>Digital Health Records</h3>
                    <p>Secure cloud storage for all your medical history, prescriptions, and test results.</p>
                </div>
                <div class="feature-card fade-in" style="transition-delay: 0.4s">
                    <div class="feature-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <h3>Medication Manager</h3>
                    <p>Automated reminders and tracking for all your prescriptions and supplements.</p>
                </div>
                <div class="feature-card fade-in" style="transition-delay: 0.5s">
                    <div class="feature-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3>Wellness Tracking</h3>
                    <p>Monitor vital signs, symptoms, and health metrics over time with insightful analytics.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-title fade-in">
                <h2>How MediMind Works</h2>
                <p>Get quality healthcare in just a few simple steps</p>
            </div>
            <div class="steps">
                <div class="step fade-in">
                    <div class="step-number">1</div>
                    <h3>Describe Your Symptoms</h3>
                    <p>Enter your symptoms and answer a few simple questions about your condition.</p>
                </div>
                <div class="step fade-in" style="transition-delay: 0.1s">
                    <div class="step-number">2</div>
                    <h3>AI Analysis</h3>
                    <p>Our AI analyzes your symptoms and provides potential conditions with confidence scores.</p>
                </div>
                <div class="step fade-in" style="transition-delay: 0.2s">
                    <div class="step-number">3</div>
                    <h3>Connect with a Doctor</h3>
                    <p>Choose to consult with a doctor immediately or schedule an appointment.</p>
                </div>
                <div class="step fade-in" style="transition-delay: 0.3s">
                    <div class="step-number">4</div>
                    <h3>Receive Treatment</h3>
                    <p>Get professional medical advice, prescriptions, and follow-up care.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-title fade-in">
                <h2>Trusted by Patients & Doctors</h2>
                <p>Hear from people who have experienced the MediMind difference</p>
            </div>
            <div class="testimonial-slider">
                <div class="testimonial-track" id="testimonialTrack">
                    <div class="testimonial fade-in">
                        <img src="https://randomuser.me/api/portraits/women/43.jpg" alt="User"
                            class="testimonial-avatar">
                        <div class="testimonial-content">
                            "MediMind's AI accurately identified my condition when three other apps failed. The video
                            consultation with Dr. Smith was seamless, and I had my prescription within 30 minutes. This
                            is the future of healthcare!"
                        </div>
                        <div class="testimonial-author">Sarah Johnson</div>
                        <div class="testimonial-role">Patient since 2022</div>
                    </div>
                    <div class="testimonial fade-in">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="User" class="testimonial-avatar">
                        <div class="testimonial-content">
                            "As an ER physician, I was skeptical about AI diagnosis, but MediMind's accuracy is
                            impressive. It helps me triage patients more efficiently and reduces unnecessary hospital
                            visits."
                        </div>
                        <div class="testimonial-author">Dr. Michael Chen</div>
                        <div class="testimonial-role">Emergency Physician</div>
                    </div>
                    <div class="testimonial fade-in">
                        <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="User"
                            class="testimonial-avatar">
                        <div class="testimonial-content">
                            "The medication tracking feature has been life-changing for managing my chronic condition. I
                            no longer miss doses, and my health has significantly improved thanks to the reminders and
                            doctor follow-ups."
                        </div>
                        <div class="testimonial-author">Emily Rodriguez</div>
                        <div class="testimonial-role">Patient with Diabetes</div>
                    </div>
                </div>
                <div class="slider-nav">
                    <div class="slider-dot active" data-index="0"></div>
                    <div class="slider-dot" data-index="1"></div>
                    <div class="slider-dot" data-index="2"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-particles" id="ctaParticles"></div>
        <div class="container">
            <div class="scale-in">
                <h2>Ready to Transform Your Healthcare Experience?</h2>
                <p>Join over 250,000 users who trust MediMind for their medical needs. Get started today - it's free!
                </p>
                <a href="includes/auth.php?register=1" class="btn">Sign Up Now</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-about fade-in">
                    <div class="footer-logo">
                        <i class="fas fa-heartbeat"></i>
                        <span>MediMind</span>
                    </div>
                    <p>Revolutionizing healthcare through AI-powered diagnosis and seamless doctor-patient connections.
                    </p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-links fade-in" style="transition-delay: 0.1s">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-links fade-in" style="transition-delay: 0.2s">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="#">AI Diagnosis</a></li>
                        <li><a href="#">Doctor Consultations</a></li>
                        <li><a href="#">Appointments</a></li>
                        <li><a href="#">Health Tracking</a></li>
                        <li><a href="#">Medication Reminders</a></li>
                    </ul>
                </div>
                <div class="footer-contact fade-in" style="transition-delay: 0.3s">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Medical Drive, Health City, HC 12345</p>
                    <p><i class="fas fa-phone-alt"></i> +1 (555) 123-4567</p>
                    <p><i class="fas fa-envelope"></i> info@medimind.com</p>
                    <p><i class="fas fa-clock"></i> Mon-Fri: 8AM-8PM EST</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 Medimind. All rights reserved. Designed at
                    <a href="https://radhakrishnawebsolution.in" target="_blank" style="color: var(--quicksand);">Radhakrishna Web Solution</a>
                    by Ravikant Upadhyay, Samriddhi Yadav and Varsha.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function () {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Scroll animation
        function checkVisibility() {
            const elements = document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right, .scale-in');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }

        // Initial check
        checkVisibility();

        // Check on scroll
        window.addEventListener('scroll', checkVisibility);

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Testimonial slider
        const track = document.getElementById('testimonialTrack');
        const dots = document.querySelectorAll('.slider-dot');
        let currentIndex = 0;
        const testimonialCount = document.querySelectorAll('.testimonial').length;

        function updateSlider() {
            track.style.transform = `translateX(-${currentIndex * 100}%)`;

            // Update dots
            dots.forEach((dot, index) => {
                if (index === currentIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }

        // Dot click events
        dots.forEach(dot => {
            dot.addEventListener('click', function () {
                currentIndex = parseInt(this.getAttribute('data-index'));
                updateSlider();
            });
        });

        // Auto slide
        setInterval(() => {
            currentIndex = (currentIndex + 1) % testimonialCount;
            updateSlider();
        }, 5000);

        // Create particles for CTA section
        const ctaParticles = document.getElementById('ctaParticles');
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');

            // Random size between 5 and 15px
            const size = Math.random() * 10 + 5;
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;

            // Random position
            particle.style.left = `${Math.random() * 100}%`;

            // Random animation duration between 10s and 20s
            const duration = Math.random() * 10 + 10;
            particle.style.animationDuration = `${duration}s`;

            // Random delay
            particle.style.animationDelay = `${Math.random() * 5}s`;

            ctaParticles.appendChild(particle);
        }

        // Rotate logo icon on hover
        const logoIcon = document.querySelector('.logo i');
        logoIcon.addEventListener('mouseover', function () {
            this.style.transform = 'rotate(360deg)';
        });
        logoIcon.addEventListener('mouseout', function () {
            this.style.transform = 'rotate(0deg)';
        });
    </script>
</body>

</html>