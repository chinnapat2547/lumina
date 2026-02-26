<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config/connectdbuser.php';

// ตั้งค่าตัวแปรเริ่มต้น (กรณีที่ยังไม่ได้ล็อกอิน)
$isLoggedIn = false;
$isAdmin = false;
$userData = [
    'u_username' => 'ผู้เยี่ยมชม',
    'u_email' => 'กรุณาเข้าสู่ระบบเพื่อใช้งาน'
];
// รูปโปรไฟล์เริ่มต้นสำหรับคนที่ยังไม่ล็อกอิน (สีเทา)
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 

// ==========================================
// 1. ตรวจสอบสถานะการล็อกอิน (Admin หรือ User)
// ==========================================
if (isset($_SESSION['admin_id'])) {
    // 🟢 กรณีที่เป็น Admin
    $isLoggedIn = true;
    $isAdmin = true;
    $admin_id = $_SESSION['admin_id'];

    $sqlAdmin = "SELECT * FROM `adminaccount` WHERE `admin_id` = ?";
    if ($stmt = mysqli_prepare($conn, $sqlAdmin)) {
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $userData['u_username'] = $row['admin_username'];
            $userData['u_email'] = 'Administrator Mode'; // เปลี่ยนอีเมลเป็นคำว่า Admin
            // ใช้รูปมงกุฎหรือสัญลักษณ์ Admin
            $profileImage = "https://ui-avatars.com/api/?name=Admin&background=a855f7&color=fff";
        }
        mysqli_stmt_close($stmt);
    }
} elseif (isset($_SESSION['u_id'])) {
    // 🔵 กรณีที่เป็น User ธรรมดา
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    $sqlUser = "SELECT a.*, u.u_image, u.u_gender 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
            
    if ($stmt = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmt, "i", $u_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $userData = $row;
            
            $displayName = $userData['u_username'] ?? 'ผู้ใช้งาน';
            if (!empty($userData['u_image']) && file_exists("profile/uploads/" . $userData['u_image'])) {
                $profileImage = "profile/uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// ตรวจสอบตะกร้าสินค้า (เฉพาะ User เท่านั้น)
$totalCartItems = 0;
if (isset($u_id) && !$isAdmin) {
    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
            $totalCartItems = $rowCartCount['total_qty'] ?? 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty - Home</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              primary: "#ec2d88", // Hot pink from button
              secondary: "#fca5a5", // Soft pink
              accent: "#a5b4fc", // Soft purple/blue
              "background-light": "#ffffff",
              "background-dark": "#18181b",
              "surface-light": "#fff5f9", // Very light pink background
              "surface-dark": "#27272a",
              "card-light": "#ffffff",
              "card-dark": "#27272a",
              "text-main-light": "#1f2937",
              "text-main-dark": "#f3f4f6",
              "input-bg": "#fff0f6", // Light pinkish input bg
            },
            fontFamily: {
              display: ["Prompt", "sans-serif"],
              sans: ["Prompt", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "1rem",
              'xl': "1.5rem",
              '2xl': "2rem",
            },
            boxShadow: {
              'soft': '0 4px 20px -2px rgba(236, 45, 136, 0.1)',
              'glow': '0 0 15px rgba(236, 45, 136, 0.3)',
            }
          },
        },
      };
    </script>
<style>html { scroll-behavior: smooth; }@keyframes gradient-xy {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .animate-gradient {
            background-size: 200% 200%;
            animation: gradient-xy 15s ease infinite;
        }.no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="font-sans bg-background-light dark:bg-background-dark text-text-main-light dark:text-text-main-dark transition-colors duration-300">
<nav class="sticky top-0 z-50 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-b border-pink-100 dark:border-gray-800 font-prompt">
    <div class="w-full px-4 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
        <div class="flex-shrink-0 flex items-center gap-2 cursor-pointer">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
        </div>

        <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-20">
            <a class="group flex flex-col items-center justify-center transition" href="shop/products.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">สินค้า</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Shop)</span>
            </a>
            
            <div class="relative group">
                <button class="flex flex-col items-center justify-center transition pb-2 pt-2">
                    <div class="flex items-center gap-1">
                        <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">หมวดหมู่</span>
                        <span class="material-icons-round text-sm text-gray-700 dark:text-gray-200 group-hover:text-primary">expand_more</span>
                    </div>
                    <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Categories)</span>
                </button>
                <div class="absolute left-1/2 -translate-x-1/2 hidden pt-1 w-48 z-50 group-hover:block">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden py-2">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">TONERPADS (โทนเนอร์แพด)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">BLUSH (บลัชออน)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">LIPS (ริมฝีปาก)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">SKIN (ผิว)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">EYES (ตา)</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-pink-50 dark:hover:bg-gray-700 hover:text-primary transition">ACCESSORIES (อุปกรณ์เสริม)</a>
                    </div>
                </div>
            </div>

            <a class="group flex flex-col items-center justify-center transition" href="shop/promotions.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">โปรโมชั่น</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Sale)</span>
            </a>
            <a class="group flex flex-col items-center justify-center transition" href="auth/contact.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">ติดต่อเรา</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Contact)</span>
            </a>
        </div>

        <div class="flex items-center space-x-3 sm:space-x-5 text-gray-600 dark:text-gray-300">
            <a href="shop/favorites.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">favorite_border</span>
            </a>
            <a href="shop/cart.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">shopping_bag</span>
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800">
                    <?= $totalCartItems ?>
                </span>
            </a>
            
            <button class="hover:text-primary transition flex items-center justify-center" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl text-gray-500">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
            </button>

            <div class="relative group flex items-center">
                <a href="<?= $isAdmin ? 'admin/dashboard.php' : 'profile/account.php' ?>" class="block w-10 h-10 rounded-full bg-gradient-to-tr <?= $isAdmin ? 'from-purple-400 to-indigo-400' : 'from-pink-300 to-purple-300' ?> p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                        <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                    </div>
                </a>
                
                <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                        
                        <div class="text-center mb-4">
                            <span class="text-sm font-medium <?= $isAdmin ? 'text-purple-500 font-bold' : 'text-gray-500 dark:text-gray-400' ?>">
                                <?= $isLoggedIn ? htmlspecialchars($userData['u_email']) : 'กรุณาเข้าสู่ระบบเพื่อใช้งาน' ?>
                            </span>
                        </div>

                        <div class="flex justify-center relative mb-4">
                            <div class="rounded-full p-[3px] <?= $isAdmin ? 'bg-purple-500' : 'bg-primary' ?> shadow-md">
                                <div class="bg-white dark:bg-gray-800 rounded-full p-[3px] w-16 h-16">
                                    <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-2 mb-6">
                            <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดี คุณ <?= htmlspecialchars($userData['u_username']) ?></h3>
                        </div>

                        <div class="flex flex-col gap-3 mt-2">
                            <?php if($isAdmin): ?>
                                <a href="admin/dashboard.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-purple-500">
                                    <span class="material-icons-round text-[20px]">admin_panel_settings</span> สำหรับ Admin
                                </a>
                                <a href="auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                                </a>
                            <?php elseif($isLoggedIn): ?>
                                <a href="profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    จัดการบัญชี
                                </a>
                                <a href="auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span> ออกจากระบบ
                                </a>
                            <?php else: ?>
                                <a href="auth/login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">login</span> เข้าสู่ระบบ
                                </a>
                                <a href="auth/register.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">person_add</span> สมัครสมาชิก
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="flex justify-center items-center gap-2 mt-5 text-[11px] text-gray-400">
                            <a href="#" class="hover:text-primary">นโยบายความเป็นส่วนตัว</a>
                            <span>•</span>
                            <a href="#" class="hover:text-primary">ข้อกำหนดบริการ</a>
                        </div>
                    </div>
                </div>
            </div>

    </div>
</div>
</nav>
<header class="relative overflow-hidden bg-gradient-to-r from-pink-100 via-purple-100 to-blue-100 dark:from-gray-900 dark:via-purple-900 dark:to-blue-900 animate-gradient">
<div class="absolute top-20 left-10 w-32 h-32 bg-white/30 rounded-full blur-2xl animate-pulse"></div>
<div class="absolute bottom-10 right-20 w-48 h-48 bg-pink-300/20 rounded-full blur-3xl"></div>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-32 relative z-10">
<div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
<div class="space-y-6 text-center md:text-left">
<div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/50 dark:bg-black/30 backdrop-blur-sm border border-white/50 shadow-sm">
<span class="material-icons-round text-primary text-sm">auto_awesome</span>
<span class="text-sm font-semibold text-primary">New Collection 2026</span>
</div>
<h1 class="text-5xl md:text-7xl font-bold leading-tight text-gray-800 dark:text-white">
                        เปล่งประกาย<br/>
<span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-purple-500">ในแบบของคุณ</span>
</h1>
<p class="text-lg text-gray-600 dark:text-gray-300 max-w-lg mx-auto md:mx-0">
                        ค้นพบผลิตภัณฑ์ดูแลผิวและเครื่องสำอางที่คัดสรรมาเพื่อคุณโดยเฉพาะ ให้คุณสวยมั่นใจได้ทุกวันกับ LuminaBeauty
                    </p>
<div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start pt-4">
<a href="shop/products.php" class="bg-primary hover:bg-pink-600 text-white px-8 py-4 rounded-full font-bold shadow-soft transform hover:-translate-y-1 transition duration-300 flex items-center justify-center gap-2 w-fit">
    ช้อปเลย (Shop Now)
    <span class="material-icons-round">arrow_forward</span>
</a>
</div>
</div>
<div class="relative h-[400px] md:h-[500px] flex items-center justify-center">
<div class="relative w-full h-full">
<div class="absolute top-10 left-10 animate-bounce" style="animation-duration: 3s;">
<div class="bg-white dark:bg-gray-700 w-24 h-16 rounded-full shadow-lg flex items-center justify-center relative opacity-90">
<div class="absolute -top-6 left-4 w-12 h-12 bg-white dark:bg-gray-700 rounded-full"></div>
<div class="absolute -top-4 right-4 w-10 h-10 bg-white dark:bg-gray-700 rounded-full"></div>
<div class="flex gap-2 mt-2">
<div class="w-2 h-2 bg-gray-800 dark:bg-gray-200 rounded-full"></div>
<div class="w-2 h-2 bg-gray-800 dark:bg-gray-200 rounded-full"></div>
</div>
</div>
</div>
<div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-auto z-20">
<img alt="Premium Cosmetic Products" class="rounded-3xl shadow-2xl rotate-3 border-4 border-white dark:border-gray-700" src="https://via.placeholder.com/400x500.png?text=Cosmetic+Product"/>
</div>
<div class="absolute bottom-20 right-10 animate-bounce" style="animation-duration: 4s;">
<div class="bg-pink-100 dark:bg-gray-600 w-28 h-20 rounded-full shadow-lg flex items-center justify-center relative opacity-90">
<div class="absolute -top-8 left-4 w-14 h-14 bg-pink-100 dark:bg-gray-600 rounded-full"></div>
<div class="absolute -top-6 right-4 w-12 h-12 bg-pink-100 dark:bg-gray-600 rounded-full"></div>
<div class="flex flex-col items-center mt-2 gap-1">
<div class="flex gap-4">
<div class="w-2 h-2 bg-gray-800 dark:bg-gray-200 rounded-full"></div>
<div class="w-2 h-2 bg-gray-800 dark:bg-gray-200 rounded-full"></div>
</div>
<div class="w-2 h-1 bg-pink-400 rounded-full"></div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</header>
<section class="py-16 bg-surface-light dark:bg-background-dark">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<h2 class="text-3xl font-bold text-center mb-10 text-gray-800 dark:text-white">หมวดหมู่สินค้า (Categories)</h2>
<div class="flex overflow-x-auto gap-8 pt-4 pb-8 no-scrollbar md:justify-center px-4">
    
    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-blue-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-blue-500 group-hover:text-white dark:text-blue-300">water_drop</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">TONERPADS<br>(โทนเนอร์แพด)</span>
    </div>

    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-pink-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-pink-500 group-hover:text-white dark:text-pink-300">face_retouching_natural</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">BLUSH<br>(บลัชออน)</span>
    </div>

    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-red-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-red-500 group-hover:text-white dark:text-red-300">favorite</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">LIPS<br>(ริมฝีปาก)</span>
    </div>

    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-green-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-green-500 group-hover:text-white dark:text-green-300">spa</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">SKIN<br>(ผิว)</span>
    </div>

    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-purple-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-purple-500 group-hover:text-white dark:text-purple-300">visibility</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">EYES<br>(อุปกรณ์เสริม)</span>
    </div>

    <div class="flex flex-col items-center gap-3 min-w-[100px] cursor-pointer group">
        <div class="w-24 h-24 rounded-full bg-yellow-100 dark:bg-gray-800 flex items-center justify-center shadow-md group-hover:scale-110 transition duration-300 group-hover:bg-primary group-hover:text-white">
            <span class="material-icons-round text-4xl text-yellow-600 group-hover:text-white dark:text-yellow-300">brush</span>
        </div>
        <span class="font-medium text-gray-700 dark:text-gray-300 group-hover:text-primary dark:group-hover:text-primary transition text-center text-sm leading-snug">ACCESSORIES<br>(อุปกรณ์เสริม)</span>
    </div>

</div>
</div>
</section>
<section class="py-16 bg-white dark:bg-background-dark">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="flex justify-between items-end mb-10">
<div>
<span class="text-primary font-bold tracking-wider text-sm uppercase">Recommended for you</span>
<h2 class="text-3xl font-bold text-gray-800 dark:text-white mt-2">สินค้าแนะนำ (Featured)</h2>
</div>
<a class="text-gray-500 hover:text-primary flex items-center gap-1 font-medium transition" href="#">
                    ดูทั้งหมด <span class="material-icons-round">chevron_right</span>
</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
    
</div>

</div>
</section>
<section class="py-20 bg-blue-50 dark:bg-gray-900 relative overflow-hidden">
<div class="absolute top-10 left-20 opacity-30 animate-pulse">
<span class="material-icons-round text-8xl text-white dark:text-gray-700">cloud</span>
</div>
<div class="absolute bottom-10 right-20 opacity-30 animate-pulse delay-700">
<span class="material-icons-round text-9xl text-white dark:text-gray-700">cloud</span>
</div>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
<div class="bg-white/60 dark:bg-gray-800/60 backdrop-blur-lg rounded-3xl p-8 md:p-16 text-center border border-white/50 dark:border-gray-700 shadow-xl">

<span class="material-icons-round text-primary text-6xl mb-6">local_florist</span>

<h2 class="text-3xl md:text-4xl font-bold text-gray-800 dark:text-white mb-6">ความงามที่มาจากธรรมชาติ</h2>
<p class="text-lg text-gray-600 dark:text-gray-300 max-w-3xl mx-auto leading-relaxed mb-8">
                    ที่ LuminaBeauty เราเชื่อว่าความสวยที่แท้จริงเริ่มต้นจากการดูแลตัวเองด้วยความรัก เราคัดสรรส่วนผสมจากธรรมชาติที่อ่อนโยนที่สุด 
                    เพื่อให้ผิวของคุณได้รับการดูแลอย่างทะนุถนอม ปราศจากสารเคมีรุนแรง และเป็นมิตรต่อสิ่งแวดล้อม
                </p>
<div class="flex flex-wrap justify-center gap-8">
<div class="flex items-center gap-2">
<span class="material-icons-round text-green-500">check_circle</span>
<span class="font-medium text-gray-700 dark:text-gray-300">100% Organic</span>
</div>
<div class="flex items-center gap-2">
<span class="material-icons-round text-green-500">check_circle</span>
<span class="font-medium text-gray-700 dark:text-gray-300">Cruelty Free</span>
</div>
<div class="flex items-center gap-2">
<span class="material-icons-round text-green-500">check_circle</span>
<span class="font-medium text-gray-700 dark:text-gray-300">Eco Friendly</span>
</div>
</div>
</div>
</div>
</section>
<section class="py-16 bg-gradient-to-r from-pink-500 to-purple-600 text-white">
<div class="max-w-4xl mx-auto px-4 text-center">
<h2 class="text-3xl font-bold mb-4">รับส่วนลด 10% สำหรับการสั่งซื้อครั้งแรก!</h2>
<p class="mb-8 opacity-90">สมัครสมาชิกรับข่าวสารเพื่อไม่พลาดโปรโมชั่นสุดพิเศษและสินค้าใหม่ๆ ก่อนใคร</p>
<form class="flex flex-col sm:flex-row gap-4 justify-center max-w-lg mx-auto">
<input class="w-full px-6 py-3 rounded-full text-gray-800 focus:outline-none focus:ring-2 focus:ring-white/50 bg-white border-0" placeholder="กรอกอีเมลของคุณ (Enter your email)" type="email"/>
<button class="bg-gray-900 hover:bg-gray-800 text-white px-8 py-3 rounded-full font-bold transition shadow-lg whitespace-nowrap" type="submit">
                    สมัครรับข่าวสาร
                </button>
</form>
</div>
</section>
<footer class="bg-surface-light dark:bg-background-dark pt-16 pb-8 border-t border-pink-100 dark:border-gray-800">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
<div class="col-span-1 md:col-span-1">
<div class="flex items-center gap-2 mb-6">
<span class="material-icons-round text-primary text-4xl">spa</span>
<span class="font-bold text-xl text-primary">LuminaBeauty</span>
</div>
<p class="text-gray-500 dark:text-gray-400 text-sm mb-6">
                        ร้านเครื่องสำอางและสกินแคร์ออนไลน์ที่คุณวางใจได้ สินค้าแท้ 100% ส่งตรงถึงหน้าบ้านคุณ
                    </p>
<div class="flex gap-4">
<a class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-gray-500 hover:text-blue-600 hover:shadow-md transition" href="#">
<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path clip-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" fill-rule="evenodd"></path></svg>
</a>
<a class="w-10 h-10 rounded-full bg-white dark:bg-gray-800 flex items-center justify-center text-gray-500 hover:text-blue-400 hover:shadow-md transition" href="#">
<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"></path></svg>
</a>
</div>
</div>
<div>
<h3 class="font-bold text-gray-800 dark:text-white mb-4">บริการลูกค้า</h3>
<ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
<li><a class="hover:text-primary transition" href="#">คำถามที่พบบ่อย (FAQ)</a></li>
<li><a class="hover:text-primary transition" href="#">วิธีสั่งซื้อสินค้า</a></li>
<li><a class="hover:text-primary transition" href="#">แจ้งชำระเงิน</a></li>
<li><a class="hover:text-primary transition" href="#">ตรวจสอบสถานะสินค้า</a></li>
</ul>
</div>
<div>
<h3 class="font-bold text-gray-800 dark:text-white mb-4">เกี่ยวกับเรา</h3>
<ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
<li><a class="hover:text-primary transition" href="#">เรื่องราวของ Lumina</a></li>
<li><a class="hover:text-primary transition" href="#">ติดต่อเรา</a></li>
<li><a class="hover:text-primary transition" href="#">ร่วมงานกับเรา</a></li>
<li><a class="hover:text-primary transition" href="#">นโยบายความเป็นส่วนตัว</a></li>
</ul>
</div>
<div>
<h3 class="font-bold text-gray-800 dark:text-white mb-4">นโยบาย</h3>
<ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
<li><a class="hover:text-primary transition" href="#">นโยบายการจัดส่ง</a></li>
<li><a class="hover:text-primary transition" href="#">นโยบายการคืนสินค้า</a></li>
<li><a class="hover:text-primary transition" href="#">เงื่อนไขการใช้บริการ</a></li>
</ul>
</div>
</div>
<div class="border-t border-gray-200 dark:border-gray-800 pt-8 text-center text-sm text-gray-500 dark:text-gray-400">
<p>© 2026 Lumina Beauty. All rights reserved.</p>
</div>
</div>
</footer>

<script>
    // 1. ฟังก์ชันทำงานอัตโนมัติเมื่อโหลดหน้าเว็บ: เช็คว่าเคยเซฟธีมมืดไว้ไหม?
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }

    // 2. ฟังก์ชันเมื่อกดปุ่ม: สลับธีมและเซฟค่าลงระบบ
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        
        // เช็คว่าตอนนี้เป็นธีมมืดหรือสว่าง แล้วเซฟทับลงไป
        if (htmlEl.classList.contains('dark')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
    }
</script>

</body></html>