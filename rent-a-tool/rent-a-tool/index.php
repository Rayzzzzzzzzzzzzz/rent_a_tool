<?php
require_once 'includes/db.php';

// Get featured tools
$featured_tools = $conn->query("
    SELECT t.*, c.name as category_name, u.fullname as owner_name 
    FROM tools t 
    LEFT JOIN categories c ON t.category_id = c.id 
    LEFT JOIN users u ON t.owner_id = u.id 
    WHERE t.status = 'available' 
    ORDER BY t.created_at DESC 
    LIMIT 8
");

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name LIMIT 6");

// Get stats for homepage
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM tools");
$stats['tools'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$stats['users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM rentals WHERE rental_status = 'completed'");
$stats['rentals'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent-A-Tool - P2P Tool Rental Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">
                    <i class="bi bi-tools"></i>
                    <span>Rent-A-Tool</span>
                </a>
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="pages/user/browse-tools.php">Browse Tools</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#categories">Categories</a></li>
                </ul>
                <div class="user-menu">
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <a href="pages/admin/dashboard.php" class="btn btn-primary">Dashboard</a>
                        <?php else: ?>
                            <a href="pages/user/dashboard.php" class="btn btn-primary">My Account</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline">Sign In</a>
                        <a href="register.php" class="btn btn-primary">Get Started</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Rent Tools, Save Money</h1>
            <p>Access thousands of tools from your community. Rent what you need, when you need it, at a fraction of the cost.</p>
            <form action="pages/user/browse-tools.php" method="GET" class="search-bar">
                <input type="text" name="search" class="form-control" placeholder="Search for tools (drill, mower, saw...)">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
            </form>
            <div style="display: flex; justify-content: center; gap: 3rem; margin-top: 3rem; flex-wrap: wrap;">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['tools']; ?>+</div>
                    <div style="opacity: 0.8;">Tools Available</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['users']; ?>+</div>
                    <div style="opacity: 0.8;">Active Users</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo $stats['rentals']; ?>+</div>
                    <div style="opacity: 0.8;">Successful Rentals</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section id="categories" class="container" style="padding: 4rem 0;">
    <h2 style="text-align: center; margin-bottom: 2rem;">Browse by Category</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">
        <?php while ($cat = $categories->fetch_assoc()): ?>
            <a href="pages/user/browse-tools.php?category=<?php echo $cat['id']; ?>" 
               style="background: white; padding: 2rem; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow); transition: transform 0.2s; text-decoration: none; color: inherit;"
               onmouseover="this.style.transform='translateY(-4px)'" 
               onmouseout="this.style.transform='translateY(0)'">

                <h4 style="margin-bottom: 0.5rem;">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </h4>

                <p style="color: var(--gray-500); font-size: 0.875rem;">
                    <?php echo htmlspecialchars($cat['description']); ?>
                </p>

            </a>
        <?php endwhile; ?>
    </div>
</section>

    <!-- Featured Tools -->
    <section class="container" style="padding: 4rem 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>Featured Tools</h2>
            <a href="pages/user/browse-tools.php" class="btn btn-outline">View All Tools</a>
        </div>
        <div class="tools-grid">
            <?php while ($tool = $featured_tools->fetch_assoc()): ?>
                <div class="tool-card">
                    <div class="tool-image">
                        <img src="assets/images/<?php echo $tool['image']; ?>" alt="<?php echo htmlspecialchars($tool['name']); ?>">
                        <div class="tool-status">
                            <span class="badge badge-success">Available</span>
                        </div>
                    </div>
                    <div class="tool-content">
                        <div class="tool-category"><?php echo htmlspecialchars($tool['category_name']); ?></div>
                        <h4 class="tool-title"><?php echo htmlspecialchars($tool['name']); ?></h4>
                        <p class="tool-description"><?php echo htmlspecialchars($tool['description']); ?></p>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; color: var(--gray-500);">
                            <i class="bi bi-geo-alt"></i>
                            <?php echo htmlspecialchars($tool['location']); ?>
                        </div>
                        <div class="tool-meta">
                            <div class="tool-price">
                                <span class="daily"><?php echo formatCurrency($tool['daily_rate']); ?>/day</span>
                                <span class="deposit">Deposit: <?php echo formatCurrency($tool['security_deposit']); ?></span>
                            </div>
                            <a href="pages/user/rent-tool.php?id=<?php echo $tool['id']; ?>" class="btn btn-primary btn-sm">
                                Rent Now
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" style="background: var(--gray-100); padding: 4rem 0;">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 3rem;">How It Works</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
                        <i class="bi bi-search"></i>
                    </div>
                    <h4 style="margin-bottom: 1rem;">1. Find a Tool</h4>
                    <p style="color: var(--gray-600);">Browse our extensive collection of tools and find exactly what you need for your project.</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h4 style="margin-bottom: 1rem;">2. Book Your Dates</h4>
                    <p style="color: var(--gray-600);">Select your rental period and pay the rental fee plus a refundable security deposit.</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
                        <i class="bi bi-tools"></i>
                    </div>
                    <h4 style="margin-bottom: 1rem;">3. Pick Up & Use</h4>
                    <p style="color: var(--gray-600);">Collect the tool from the owner, complete your project, and return it on time.</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4 style="margin-bottom: 1rem;">4. Get Deposit Back</h4>
                    <p style="color: var(--gray-600);">Return the tool in good condition and receive your security deposit refund within 3-5 days.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="container" style="padding: 4rem 0;">
        <h2 style="text-align: center; margin-bottom: 3rem;">Why Choose Rent-A-Tool?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div style="display: flex; gap: 1rem; padding: 1.5rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow);">
                <div style="color: var(--success); font-size: 2rem;">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <h4>Secure Deposits</h4>
                    <p style="color: var(--gray-600); margin: 0;">Your security deposit is safely held and only released upon successful tool return.</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; padding: 1.5rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow);">
                <div style="color: var(--primary); font-size: 2rem;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <h4>Late Fee Protection</h4>
                    <p style="color: var(--gray-600); margin: 0;">Automatic late fee calculation ensures fair compensation for delayed returns.</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; padding: 1.5rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow);">
                <div style="color: var(--warning); font-size: 2rem;">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div>
                    <h4>Condition Reporting</h4>
                    <p style="color: var(--gray-600); margin: 0;">Document tool condition before and after rental for complete transparency.</p>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; padding: 1.5rem; background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow);">
                <div style="color: var(--info); font-size: 2rem;">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h4>Community Driven</h4>
                    <p style="color: var(--gray-600); margin: 0;">Connect with local tool owners and build a sharing community in your neighborhood.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section style="background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); padding: 4rem 0; text-align: center;">
        <div class="container">
            <h2 style="color: white; margin-bottom: 1rem;">Ready to Start Renting?</h2>
            <p style="color: rgba(255,255,255,0.9); font-size: 1.25rem; max-width: 600px; margin: 0 auto 2rem;">
                Join thousands of users who save money by renting tools instead of buying them.
            </p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <a href="register.php" class="btn btn-lg" style="background: white; color: var(--primary);">
                    <i class="bi bi-person-plus"></i> Create Account
                </a>
                <a href="pages/user/browse-tools.php" class="btn btn-lg btn-outline" style="border-color: white; color: white;">
                    <i class="bi bi-search"></i> Browse Tools
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="bi bi-tools"></i> Rent-A-Tool</h4>
                    <p style="color: var(--gray-500);">The premier P2P tool rental platform. Save money, reduce waste, and build community.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="pages/user/browse-tools.php">Browse Tools</a></li>
                        <li><a href="login.php">Sign In</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Safety Guidelines</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <ul>
                        <li><i class="bi bi-envelope"></i> support@rentatool.com</li>
                        <li><i class="bi bi-telephone"></i> 1-800-RENT-TOOL</li>
                        <li><i class="bi bi-geo-alt"></i> 123 Tool Street, City</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Rent-A-Tool. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
