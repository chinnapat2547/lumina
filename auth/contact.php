<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php';

// ==========================================
// 1. จัดการข้อมูลผู้ใช้และตะกร้า (สำหรับ Navbar)
// ==========================================
$isLoggedIn = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$userData = ['u_username' => 'ผู้เยี่ยมชม', 'u_email' => 'กรุณาเข้าสู่ระบบ'];

if (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    $sqlUser = "SELECT a.u_username, a.u_email, u.u_image 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        if ($rowUser = mysqli_fetch_assoc($resultUser)) {
            $userData = $rowUser;
            if (!empty($userData['u_image']) && file_exists("../profile/uploads/" . $userData['u_image'])) {
                $profileImage = "../profile/uploads/" . $userData['u_image'];
            } else {
                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
            }
        }
        mysqli_stmt_close($stmtUser);
    }
    
$totalCartItems = 0;
    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
            $totalCartItems = $rowCartCount['total_qty'] ?? 0; // ถ้าเป็น null ให้เป็น 0
        }
        mysqli_stmt_close($stmtCartCount);
    }
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>ติดต่อเรา - Lumina Beauty</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#F43F85",
                        secondary: "#FBCFE8",
                        accent: "#A78BFA",
                        "background-light": "#FFF5F7",
                        "background-dark": "#1F1B24",
                        "surface-light": "#FFFFFF",
                        "surface-dark": "#2D2635",
                        "text-light": "#374151",
                        "text-dark": "#E5E7EB",
                    },
                    fontFamily: {
                        display: ["Prompt", "sans-serif"],
                        body: ["Prompt", "sans-serif"],
                    },
                    borderRadius: {
                        "DEFAULT": "1rem",
                        "lg": "1.5rem",
                        "xl": "2rem",
                        "2xl": "2.5rem",
                        "3xl": "3rem",
                        "full": "9999px"
                    },
                    boxShadow: {
                        'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
                        'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'bounce-slow': 'bounce 3s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                },
            },
        }
    </script>
<style>
        body { font-family: 'Prompt', sans-serif; }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .dark .glass-panel {
            background: rgba(45, 38, 53, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .bg-cloud-pattern {
            background-color: transparent;
            background-image: radial-gradient(rgba(244, 63, 133, 0.15) 1px, transparent 1px);
            background-size: 24px 24px;
        }
        .dark .bg-cloud-pattern {
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark font-body transition-colors duration-300 min-h-screen flex flex-col overflow-x-hidden">

<nav class="sticky top-0 z-50 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-b border-pink-100 dark:border-gray-800 font-prompt">
    <div class="w-full px-6 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
        <a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
            <span class="material-icons-round text-primary text-4xl">spa</span>
            <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
        </a>

        <div class="hidden lg:flex gap-8 xl:gap-12 items-center justify-center flex-grow ml-20">
            <a class="group flex flex-col items-center justify-center transition" href="../shop/products.php">
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

            <a class="group flex flex-col items-center justify-center transition" href="../shop/promotions.php">
                <span class="text-[18px] font-bold text-gray-700 dark:text-gray-200 group-hover:text-primary dark:group-hover:text-primary leading-tight">โปรโมชั่น</span>
                <span class="text-[13px] text-gray-500 dark:text-gray-400 group-hover:text-primary dark:group-hover:text-primary">(Sale)</span>
            </a>
            <a class="flex flex-col items-center justify-center cursor-default pointer-events-none">
                <span class="text-[18px] font-bold text-primary dark:text-primary leading-tight">ติดต่อเรา</span>
                <span class="text-[13px] text-primary/80 dark:text-primary/80">(Contact)</span>
            </a>
        </div>

        <div class="flex items-center space-x-3 sm:space-x-5 text-gray-600 dark:text-gray-300">
            <a href="../shop/favorites.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">favorite_border</span>
            </a>
            <a href="../shop/cart.php" class="hover:text-primary transition relative flex items-center">
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
                <a href="../profile/account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
                        <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
                    </div>
                </a>
                <div class="absolute right-0 hidden pt-4 top-full w-[320px] z-50 group-hover:block cursor-default">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-[0_10px_40px_-10px_rgba(236,45,136,0.2)] border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                        
                        <div class="text-center mb-4">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                <?= $isLoggedIn ? htmlspecialchars($userData['u_email']) : 'กรุณาเข้าสู่ระบบเพื่อใช้งาน' ?>
                            </span>
                        </div>

                        <div class="flex justify-center relative mb-4">
                            <div class="rounded-full p-[3px] bg-primary shadow-md">
                                <div class="bg-white dark:bg-gray-800 rounded-full p-[3px] w-16 h-16">
                                    <?php 
                                        $displayName = $isLoggedIn ? $userData['u_username'] : 'ผ'; 
                                    ?>
                                    <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-2 mb-6">
                            <h3 class="text-[22px] font-bold text-gray-800 dark:text-white">สวัสดีคุณ <?= htmlspecialchars($userData['u_username']) ?></h3>
                        </div>

                        <div class="flex flex-col gap-3 mt-2">
                            <?php if($isLoggedIn): ?>
                                <a href="../profile/account.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    จัดการบัญชี
                                </a>
                                <a href="logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[20px]">logout</span>
                                    ออกจากระบบ
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">login</span>
                                    เข้าสู่ระบบ
                                </a>
                                <a href="register.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-primary hover:bg-primary hover:text-white rounded-full py-2.5 transition text-[15px] font-semibold text-primary">
                                    <span class="material-icons-round text-[20px]">person_add</span>
                                    สมัครสมาชิก
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
</header>

<main class="flex-grow bg-cloud-pattern pb-12">
    <div class="relative w-full overflow-hidden bg-gradient-to-br from-pink-50 via-purple-50 to-pink-100 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 pt-16 pb-24 rounded-b-[3rem] shadow-soft mb-12 border-b border-pink-100 dark:border-gray-800">
        
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-white/40 dark:bg-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 bg-primary/20 rounded-full blur-3xl"></div>
        
        <div class="max-w-7xl mx-auto px-6 lg:px-10 flex flex-col-reverse md:flex-row items-center justify-between relative z-10">
            <div class="flex flex-col gap-4 text-center md:text-left mt-8 md:mt-0 max-w-lg">
                <span class="inline-block px-4 py-1.5 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur text-primary text-xs font-bold uppercase tracking-wider shadow-sm w-fit mx-auto md:mx-0 border border-pink-100 dark:border-gray-700">
                    ✨ พร้อมดูแลคุณ 24 ชม.
                </span>
                <h1 class="text-4xl md:text-6xl font-black text-gray-800 dark:text-white leading-tight">
                    ติดต่อเรา <br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-pink-400">ได้ง่ายๆ</span>
                </h1>
                <p class="text-gray-600 dark:text-gray-300 text-lg md:text-xl font-medium leading-relaxed">
                    มีข้อสงสัยเรื่องผิว หรือต้องการคำปรึกษา? <br class="hidden md:inline"/>ทีมงาน Lumina Beauty พร้อมดูแลคุณเสมอค่ะ
                </p>
            </div>
            
            <div class="relative w-full md:w-1/2 flex justify-center md:justify-end">
                <div class="relative w-64 h-64 md:w-80 md:h-80 lg:w-96 lg:h-96">
                    <img alt="Contact us illustration" class="w-full h-full object-contain drop-shadow-2xl animate-bounce-slow" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBek-t0Fcaj95CLkhHwsz7_bptvZfd0ztsvO-57dEJlTb414jqsld9B8vpYS9un_yA0ZTB5bEz7S_g8ecB4xrZkaz-djg20-4s1sK7J_E0R2tRh_6kUSyqIGuBF2CEjNggFexgI_1drj-EwqwW_MbfdpwlZ8K9-ASnyiQ7qZsRdQj8-DJmWxXYCCAIttDn39k856-09MgJ5pTD0lM5gls5a3FkpxHVIT1mGYuX3K6W50CbeAPwcDzq-izWxhGqiH1AO2ZlJh8kA8C7y" style="mask-image: radial-gradient(circle, black 60%, transparent 100%); -webkit-mask-image: radial-gradient(circle, black 60%, transparent 100%);"/>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 relative z-20">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <div class="lg:col-span-5 flex flex-col gap-6">
                <div class="bg-white dark:bg-surface-dark rounded-3xl p-8 shadow-soft flex flex-col gap-6 border border-pink-50 dark:border-gray-800">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <span class="text-2xl">📍</span> ข้อมูลติดต่อ
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-pink-50/50 dark:bg-gray-800/50 hover:bg-pink-50 dark:hover:bg-gray-800 transition-colors cursor-default group border border-transparent hover:border-pink-100 dark:hover:border-gray-700">
                            <div class="w-12 h-12 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center text-primary shadow-sm group-hover:scale-110 transition-transform flex-shrink-0">
                                <span class="material-icons-round">location_on</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-primary opacity-80 uppercase mb-1">ที่อยู่</p>
                                <p class="text-gray-700 dark:text-gray-300 font-medium leading-relaxed">999 อาคารลูมิน่า ชั้น 999 ถนนอวกาศ<br/>แขวงดาวเสาร์ เขตทางช้างเผือก<br/>จักรวาล หรือ เอกภพ 99999</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-pink-50/50 dark:bg-gray-800/50 hover:bg-pink-50 dark:hover:bg-gray-800 transition-colors cursor-default group border border-transparent hover:border-pink-100 dark:hover:border-gray-700">
                            <div class="w-12 h-12 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center text-primary shadow-sm group-hover:scale-110 transition-transform flex-shrink-0">
                                <span class="material-icons-round">call</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-primary opacity-80 uppercase mb-1">เบอร์โทรศัพท์</p>
                                <p class="text-gray-700 dark:text-white font-bold text-lg">02-123-4567</p>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">จันทร์ - ศุกร์ (09:00 - 18:00)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4 p-4 rounded-2xl bg-pink-50/50 dark:bg-gray-800/50 hover:bg-pink-50 dark:hover:bg-gray-800 transition-colors cursor-default group border border-transparent hover:border-pink-100 dark:hover:border-gray-700">
                            <div class="w-12 h-12 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center text-primary shadow-sm group-hover:scale-110 transition-transform flex-shrink-0">
                                <span class="material-icons-round">mail</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-primary opacity-80 uppercase mb-1">อีเมล</p>
                                <p class="text-gray-700 dark:text-white font-bold text-lg">hello@luminabeauty.com</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-dashed border-gray-200 dark:border-gray-700 pt-6 mt-2">
                        <p class="text-center text-gray-500 dark:text-gray-400 font-medium mb-4">ติดตามเราได้ที่</p>
                        <div class="flex justify-center gap-4">
                            <a href="#" class="w-12 h-12 rounded-full bg-[#06C755] text-white flex items-center justify-center hover:-translate-y-1 transition-transform shadow-md shadow-[#06C755]/20">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.92 2 10.75c0 4.35 3.65 7.97 8.5 8.6v.05l-.8 2.5c-.1.35.2.6.5.4l4.2-2.8c4.35-.55 7.6-3.85 7.6-7.75C22 5.92 17.52 2 12 2z"></path></svg>
                            </a>
                            <a href="#" class="w-12 h-12 rounded-full bg-[#1877F2] text-white flex items-center justify-center hover:-translate-y-1 transition-transform shadow-md shadow-[#1877F2]/20">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.17 6 13.5 6c1.12 0 2.23.2 2.23.2v2.45h-1.26c-1.2 0-1.57.75-1.57 1.5V12h2.77l-.44 3h-2.33v6.8c4.56-.93 8-4.96 8-9.8z"></path></svg>
                            </a>
                            <a href="#" class="w-12 h-12 rounded-full bg-gradient-to-tr from-[#FFD600] via-[#FF0100] to-[#D800B9] text-white flex items-center justify-center hover:-translate-y-1 transition-transform shadow-md shadow-[#D800B9]/20">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect height="20" rx="5" ry="5" width="20" x="2" y="2"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="bg-white dark:bg-surface-dark rounded-3xl p-8 lg:p-10 shadow-soft border border-pink-50 dark:border-gray-800 h-full flex flex-col justify-center">
                    <div class="mb-8 text-center md:text-left">
                        <h3 class="text-3xl font-black text-gray-800 dark:text-white mb-2">ส่งข้อความหาเรา 💌</h3>
                        <p class="text-gray-500 dark:text-gray-400">กรอกข้อมูลด้านล่าง ทีมงานจะรีบตอบกลับภายใน 24 ชม. ค่ะ</p>
                    </div>
                    
                    <form action="#" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-gray-700 dark:text-gray-300 ml-1">ชื่อ-นามสกุล</label>
                                <input class="w-full bg-pink-50/50 dark:bg-gray-800 border-none rounded-2xl px-5 py-4 text-gray-800 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-primary/50 transition-all outline-none" placeholder="ระบุชื่อของคุณ" type="text"/>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-bold text-gray-700 dark:text-gray-300 ml-1">อีเมล</label>
                                <input class="w-full bg-pink-50/50 dark:bg-gray-800 border-none rounded-2xl px-5 py-4 text-gray-800 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-primary/50 transition-all outline-none" placeholder="example@mail.com" type="email"/>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700 dark:text-gray-300 ml-1">หัวข้อ</label>
                            <div class="relative">
                                <select class="w-full bg-pink-50/50 dark:bg-gray-800 border-none rounded-2xl px-5 py-4 text-gray-800 dark:text-white focus:ring-2 focus:ring-primary/50 transition-all appearance-none cursor-pointer outline-none">
                                    <option>สอบถามข้อมูลทั่วไป</option>
                                    <option>ติดตามคำสั่งซื้อ</option>
                                    <option>แจ้งปัญหาการใช้งาน</option>
                                    <option>อื่นๆ</option>
                                </select>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                                    <span class="material-icons-round">expand_more</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700 dark:text-gray-300 ml-1">ข้อความ</label>
                            <textarea class="w-full bg-pink-50/50 dark:bg-gray-800 border-none rounded-2xl px-5 py-4 text-gray-800 dark:text-white placeholder:text-gray-400 focus:ring-2 focus:ring-primary/50 transition-all resize-none outline-none" placeholder="พิมพ์ข้อความของคุณที่นี่..." rows="4"></textarea>
                        </div>
                        
                        <button class="w-full bg-primary hover:bg-pink-600 text-white font-bold text-lg rounded-2xl py-4 shadow-md shadow-primary/30 transform active:scale-95 transition-all flex items-center justify-center gap-2 group" type="button">
                            <span>ส่งข้อความ</span>
                            <span class="material-icons-round group-hover:translate-x-1 transition-transform">send</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
</main>

<div class="fixed bottom-6 right-6 z-50 flex items-end gap-3 animate-[fade-in-up_0.5s_ease-out]">
    <div class="bg-white dark:bg-gray-800 px-4 py-3 rounded-2xl rounded-br-none shadow-soft mb-4 hidden md:block relative border border-pink-50 dark:border-gray-700">
        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">👋 คุยกับเราตอนนี้!</p>
        <div class="absolute -bottom-2 right-0 w-4 h-4 bg-white dark:bg-gray-800 transform rotate-45 border-b border-r border-pink-50 dark:border-gray-700"></div>
    </div>
    <button class="w-16 h-16 bg-gradient-to-tr from-primary to-pink-400 rounded-full shadow-lg shadow-primary/40 flex items-center justify-center text-white hover:scale-110 transition-transform cursor-pointer relative">
        <span class="material-icons-round text-[32px]">forum</span>
        <span class="absolute top-0 right-0 w-4 h-4 bg-green-400 border-2 border-white rounded-full"></span>
    </button>
</div>

<footer class="bg-white dark:bg-surface-dark py-10 border-t border-pink-50 dark:border-gray-800 relative z-20">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex justify-center items-center mb-4 opacity-80">
            <span class="text-primary material-icons-round text-3xl mr-2">spa</span>
            <span class="font-display font-bold text-2xl text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-gray-400 text-sm">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<script>
    // Script สลับ Dark Mode
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }
</script>
</body></html>