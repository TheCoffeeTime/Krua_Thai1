<?php
/**
 * Somdul Table - About Us Page
 * File: about.php (or blogs.php)
 * Description: Learn about Somdul Table's mission, chefs, and authentic Thai cuisine journey
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Our Story & Mission | Somdul Table</title>
    <meta name="description" content="Discover the story behind Somdul Table - authentic Thai cuisine crafted by expert chefs and delivered fresh to your door. Learn about our mission to bring Thailand's flavors to America.">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 200px);
        }

        /* Hero Section */
        .hero-section {
            min-height: 80vh;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            color: var(--brown);
            line-height: 1.1;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-image {
            position: relative;
            height: 500px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-medium);
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Story Section */
        .story-section {
            padding: 6rem 2rem;
            background: var(--white);
        }

        .story-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .story-title {
            font-size: 2.8rem;
            margin-bottom: 2rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .story-subtitle {
            font-size: 1.4rem;
            margin-bottom: 3rem;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
            font-style: italic;
        }

        .story-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .story-content p {
            margin-bottom: 1.5rem;
        }

        /* Values Section */
        .values-section {
            padding: 6rem 2rem;
            background: var(--cream);
        }

        .values-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .values-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .values-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .values-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .value-card {
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
        }

        .value-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .value-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .value-title {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .value-description {
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Chefs Section */
        .chefs-section {
            padding: 6rem 2rem;
            background: var(--white);
        }

        .chefs-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .chefs-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .chefs-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .chefs-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .chefs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .chef-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .chef-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .chef-image {
            width: 100%;
            height: 300px;
            background: linear-gradient(45deg, var(--brown), var(--sage));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 4rem;
            position: relative;
        }

        .chef-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chef-content {
            padding: 2rem;
        }

        .chef-name {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .chef-role {
            font-size: 1rem;
            color: var(--curry);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .chef-description {
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Mission Section */
        .mission-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, var(--brown) 0%, var(--sage) 100%);
            color: var(--white);
        }

        .mission-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .mission-title {
            font-size: 2.8rem;
            margin-bottom: 2rem;
            color: var(--white) !important;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .mission-content {
            font-size: 1.3rem;
            line-height: 1.8;
            margin-bottom: 3rem;
            font-family: 'BaticaSans', sans-serif;
            color: rgba(255, 255, 255, 0.9);
        }

        .mission-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: var(--white);
            color: var(--brown);
            border: 2px solid var(--white);
        }

        .btn-white:hover {
            background: transparent;
            color: var(--white);
        }

        .btn-outline-white {
            background: transparent;
            color: var(--white);
            border: 2px solid var(--white);
        }

        .btn-outline-white:hover {
            background: var(--white);
            color: var(--brown);
        }

        /* Testimonials Section */
        .testimonials-section {
            padding: 6rem 2rem;
            background: var(--cream);
        }

        .testimonials-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonials-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .testimonials-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .testimonials-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--white);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .testimonial-quote {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            background: var(--brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
        }

        .author-info h4 {
            color: var(--brown);
            margin: 0;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .author-info p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .stars {
            display: flex;
            gap: 0.2rem;
            margin-bottom: 1rem;
        }

        .star {
            color: #ffc107;
            font-size: 1.2rem;
        }

        /* CTA Section */
        .cta-section {
            padding: 6rem 2rem;
            background: var(--white);
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--brown);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        footer {
            background: var(--text-dark);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
            margin-top: 4rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .values-grid,
            .chefs-grid,
            .testimonials-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .story-title,
            .values-title,
            .chefs-title,
            .testimonials-title,
            .mission-title,
            .cta-title {
                font-size: 2rem;
            }

            .mission-content {
                font-size: 1.1rem;
            }

            .mission-cta,
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .story-title,
            .values-title,
            .chefs-title,
            .testimonials-title,
            .mission-title,
            .cta-title {
                font-size: 1.8rem;
            }

            .value-card,
            .chef-content,
            .testimonial-card {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero-section" id="home">
            <div class="hero-container">
                <div class="hero-content">
                    <h1>Bringing Thailand's Heart to Your Table</h1>
                    <p>We're more than a meal delivery service. We're a bridge between the bustling street markets of Bangkok and your dining room, crafted with passion by authentic Thai chefs who carry generations of culinary wisdom.</p>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <a href="#our-story" class="btn btn-primary">Our Story</a>
                        <a href="#our-chefs" class="btn btn-secondary">Meet Our Chefs</a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="assets/image/thai-chef-cooking.jpg" alt="Thai chef preparing authentic dishes" 
                         onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👨‍🍳</div>'">
                </div>
            </div>
        </section>

        <!-- Story Section -->
        <section class="story-section" id="our-story">
            <div class="story-container">
                <h2 class="story-title">Our Story</h2>
                <p class="story-subtitle">"Somdul" means "balance" in Thai — the perfect harmony of flavors that defines our cuisine</p>
                
                <div class="story-content">
                    <p>It all started with a simple longing for home. When our founder, Chef Siriporn, moved to America, she missed the vibrant flavors of her grandmother's kitchen in Bangkok. The aromatic curry pastes ground fresh each morning, the perfect balance of sweet, sour, salty, and spicy that danced on every plate.</p>

                    <p>We realized that authentic Thai food isn't just about recipes — it's about the soul, the technique, and the stories passed down through generations. That's why we partnered with master chefs directly from Thailand, each bringing their unique regional expertise and family traditions to create something unprecedented in America.</p>

                    <p>Today, Somdul Table connects food lovers across the United States with the true essence of Thai cuisine. We're not just delivering meals; we're sharing culture, preserving tradition, and creating new memories around the dinner table.</p>

                    <p>Every dish tells a story. Every meal is a journey. Welcome to our table.</p>
                </div>
            </div>
        </section>

        <!-- Values Section -->
        <section class="values-section" id="our-values">
            <div class="values-container">
                <div class="values-header">
                    <h2 class="values-title">What Drives Us</h2>
                    <p class="values-subtitle">The principles that guide every dish we create and every relationship we build</p>
                </div>

                <div class="values-grid">
                    <div class="value-card">
                        <span class="value-icon">🌿</span>
                        <h3 class="value-title">Authentic Ingredients</h3>
                        <p class="value-description">We source traditional Thai ingredients directly from trusted suppliers, ensuring every dish maintains its authentic flavor profile and cultural integrity.</p>
                    </div>

                    <div class="value-card">
                        <span class="value-icon">👨‍🍳</span>
                        <h3 class="value-title">Master Craftsmanship</h3>
                        <p class="value-description">Our chefs are trained in traditional Thai cooking methods, bringing decades of experience and family recipes to create restaurant-quality meals.</p>
                    </div>

                    <div class="value-card">
                        <span class="value-icon">🌱</span>
                        <h3 class="value-title">Sustainable Practices</h3>
                        <p class="value-description">From eco-friendly packaging to supporting local farms, we're committed to practices that respect both our planet and our communities.</p>
                    </div>

                    <div class="value-card">
                        <span class="value-icon">❤️</span>
                        <h3 class="value-title">Cultural Bridge</h3>
                        <p class="value-description">We believe food is the universal language that connects cultures, bringing the warmth of Thai hospitality to American homes.</p>
                    </div>

                    <div class="value-card">
                        <span class="value-icon">🍃</span>
                        <h3 class="value-title">Health & Wellness</h3>
                        <p class="value-description">Thai cuisine naturally emphasizes fresh vegetables, lean proteins, and balanced nutrition — supporting both taste and wellness.</p>
                    </div>

                    <div class="value-card">
                        <span class="value-icon">🤝</span>
                        <h3 class="value-title">Community First</h3>
                        <p class="value-description">We're not just feeding customers; we're building a community of food lovers who appreciate authentic flavors and cultural exchange.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Chefs Section -->
        <section class="chefs-section" id="our-chefs">
            <div class="chefs-container">
                <div class="chefs-header">
                    <h2 class="chefs-title">Meet Our Master Chefs</h2>
                    <p class="chefs-subtitle">The culinary artists who bring authentic Thai flavors to your table</p>
                </div>

                <div class="chefs-grid">
                    <div class="chef-card">
                        <div class="chef-image">
                            <img src="assets/image/chef-siriporn.jpg" alt="Chef Siriporn Pattanakul" 
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👩‍🍳</div>'">
                        </div>
                        <div class="chef-content">
                            <h3 class="chef-name">Chef Siriporn Pattanakul</h3>
                            <p class="chef-role">Head Chef & Co-Founder</p>
                            <p class="chef-description">Born in Bangkok, Chef Siriporn learned the art of Thai cooking from her grandmother. With over 15 years of experience in Bangkok's finest restaurants, she specializes in royal Thai cuisine and traditional curry preparations. Her passion lies in preserving authentic recipes while making them accessible to modern lifestyles.</p>
                        </div>
                    </div>

                    <div class="chef-card">
                        <div class="chef-image">
                            <img src="assets/image/chef-narong.jpg" alt="Chef Narong Thanakit" 
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👨‍🍳</div>'">
                        </div>
                        <div class="chef-content">
                            <h3 class="chef-name">Chef Narong Thanakit</h3>
                            <p class="chef-role">Regional Cuisine Specialist</p>
                            <p class="chef-description">Hailing from Chiang Mai in Northern Thailand, Chef Narong brings the bold, rustic flavors of his homeland to Somdul Table. His expertise in regional Thai cuisines ensures our menu represents the diverse culinary landscape of Thailand, from spicy som tam to rich khao soi.</p>
                        </div>
                    </div>

                    <div class="chef-card">
                        <div class="chef-image">
                            <img src="assets/image/chef-pranee.jpg" alt="Chef Pranee Suksomboon" 
                                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👩‍🍳</div>'">
                        </div>
                        <div class="chef-content">
                            <h3 class="chef-name">Chef Pranee Suksomboon</h3>
                            <p class="chef-role">Dessert & Street Food Expert</p>
                            <p class="chef-description">From the bustling streets of Bangkok, Chef Pranee mastered the art of Thai street food and traditional desserts. Her creative approach to classic recipes brings the vibrant energy of Thai markets to every dish, specializing in pad thai, mango sticky rice, and innovative fusion creations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission Section -->
        <section class="mission-section" id="our-mission">
            <div class="mission-container">
                <h2 class="mission-title">Our Mission</h2>
                <p class="mission-content">
                    We envision a world where authentic Thai cuisine is accessible to everyone, where busy families can experience the joy of restaurant-quality meals without compromise, and where the rich culinary traditions of Thailand continue to thrive in hearts and homes across America.
                </p>
                <p class="mission-content">
                    Through Somdul Table, we're not just delivering food — we're preserving culture, supporting authentic culinary artistry, and creating meaningful connections between Thai heritage and American tables.
                </p>
                <div class="mission-cta">
                    <a href="menus.php" class="btn btn-white">Explore Our Menu</a>
                    <a href="subscribe.php" class="btn btn-outline-white">Start Your Journey</a>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="testimonials-section" id="testimonials">
            <div class="testimonials-container">
                <div class="testimonials-header">
                    <h2 class="testimonials-title">What Our Community Says</h2>
                    <p class="testimonials-subtitle">Real stories from families who've made Somdul Table part of their lives</p>
                </div>

                <div class="testimonials-grid">
                    <div class="testimonial-card">
                        <div class="stars">
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                        </div>
                        <p class="testimonial-quote">"As someone who lived in Bangkok for three years, I can honestly say Somdul Table captures the authentic flavors I fell in love with. The green curry tastes exactly like what I had at my favorite local restaurant in Sukhumvit."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">MJ</div>
                            <div class="author-info">
                                <h4>Michael Johnson</h4>
                                <p>Food Enthusiast • Seattle, WA</p>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="stars">
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                        </div>
                        <p class="testimonial-quote">"Somdul Table has become our family's weekly tradition. My kids love the pad thai, and I love that we're getting authentic, healthy meals without the stress of cooking. It's brought our family closer around the dinner table."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">LC</div>
                            <div class="author-info">
                                <h4>Lisa Chen</h4>
                                <p>Working Mother • San Francisco, CA</p>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="stars">
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                        </div>
                        <p class="testimonial-quote">"The quality and attention to detail is incredible. You can taste the difference when chefs who truly understand Thai cuisine prepare your meals. Every dish tells a story, and I love learning about the origins through their descriptions."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">RM</div>
                            <div class="author-info">
                                <h4>Robert Martinez</h4>
                                <p>Food Blogger • Austin, TX</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section" id="get-started">
            <div class="cta-container">
                <h2 class="cta-title">Ready to Experience Authentic Thai?</h2>
                <p class="cta-subtitle">Join thousands of families who've made Somdul Table their go-to for authentic Thai cuisine. Your culinary journey to Thailand starts here.</p>
                
                <div class="cta-buttons">
                    <a href="subscribe.php" class="btn btn-primary">Start Your Subscription</a>
                    <a href="menus.php" class="btn btn-secondary">Browse Our Menu</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <img src="./assets/image/LOGO_BG2.png" alt="Somdul Table" style="height: 45px; width: auto;">
                <span style="font-size: 1.5rem; font-weight: 700;">Somdul Table</span>
            </div>
            <p style="color: var(--text-gray); margin-bottom: 0.5rem;">
                Authentic Thai flavors delivered fresh to your door
            </p>
            <p style="color: var(--text-gray); font-size: 0.9rem;">
                © 2025 Somdul Table. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        // Page-specific JavaScript for about.php
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ℹ️ About page loaded');
            
            // Smooth scrolling for navigation links
            const navLinks = document.querySelectorAll('a[href^="#"]');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        targetSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Animation on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Animate cards on scroll
            const animatedElements = document.querySelectorAll('.value-card, .chef-card, .testimonial-card');
            animatedElements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(element);
            });
        });
    </script>
</body>
</html>