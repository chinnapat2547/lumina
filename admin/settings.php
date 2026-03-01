<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// ส่วนดึงข้อมูลแจ้งเตือนสำหรับ Sidebar (ต้องมีทุกหน้า)
// ==========================================
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$newComplaints = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `contact_messages` WHERE status='pending' OR status IS NULL"))['c'] ?? 0;

// ==========================================
// 1. ตรวจสอบสิทธิ์
// ==========================================
// if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login.php"); exit(); }
$admin_id = $_SESSION['admin_id'] ?? 1; 

// ดึงข้อมูล Admin จากฐานข้อมูลล่าสุด
$adminName = 'Admin Nina';
$sqlAdmin = "SELECT admin_username FROM adminaccount WHERE admin_id = ?";
if ($stmtA = mysqli_prepare($conn, $sqlAdmin)) {
    mysqli_stmt_bind_param($stmtA, "i", $admin_id);
    mysqli_stmt_execute($stmtA);
    $resA = mysqli_stmt_get_result($stmtA);
    if ($rowA = mysqli_fetch_assoc($resA)) {
        $adminName = $rowA['admin_username'];
        $_SESSION['admin_username'] = $adminName; // อัปเดต Session ด้วย
    }
}
$adminAvatar = "https://ui-avatars.com/api/?name=" . urlencode($adminName) . "&background=a855f7&color=fff";

// ==========================================
// 2. จัดการบันทึกข้อมูล (POST & GET)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🔴 2.1 อัปเดตข้อมูลผู้ดูแลระบบ (Fix 4: ใช้งานได้จริง)
    if ($action === 'update_admin') {
        $new_username = mysqli_real_escape_string($conn, trim($_POST['admin_username']));
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                // อัปเดตทั้งชื่อและรหัสผ่าน
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sqlUpAdmin = "UPDATE `adminaccount` SET admin_username = ?, admin_password = ? WHERE admin_id = ?";
                if ($stmt = mysqli_prepare($conn, $sqlUpAdmin)) {
                    mysqli_stmt_bind_param($stmt, "ssi", $new_username, $hashed_password, $admin_id);
                    if(mysqli_stmt_execute($stmt)) {
                        $_SESSION['admin_username'] = $new_username;
                        $_SESSION['success_msg'] = "เปลี่ยนรหัสผ่านและชื่อผู้ใช้สำเร็จ!";
                    }
                }
            } else {
                $_SESSION['error_msg'] = "รหัสผ่านใหม่ไม่ตรงกัน!";
            }
        } else {
            // อัปเดตแค่ชื่อ
            $sqlUpAdmin = "UPDATE `adminaccount` SET admin_username = ? WHERE admin_id = ?";
            if ($stmt = mysqli_prepare($conn, $sqlUpAdmin)) {
                mysqli_stmt_bind_param($stmt, "si", $new_username, $admin_id);
                if(mysqli_stmt_execute($stmt)) {
                    $_SESSION['admin_username'] = $new_username;
                    $_SESSION['success_msg'] = "อัปเดตชื่อผู้ดูแลระบบสำเร็จ!";
                }
            }
        }
        header("Location: settings.php?tab=admin");
        exit();
    }
    
    // 🔴 2.2 อัปเดตข้อมูลร้านค้า (จำลอง)
    if ($action === 'update_store') {
        $_SESSION['success_msg'] = "บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว!";
        header("Location: settings.php?tab=store");
        exit();
    }

    // 🔴 2.3 อัปเดตค่าจัดส่ง (จำลอง)
    if ($action === 'update_shipping') {
        $_SESSION['success_msg'] = "อัปเดตเงื่อนไขการจัดส่งเรียบร้อยแล้ว!";
        header("Location: settings.php?tab=shipping");
        exit();
    }

    // 🔴 2.4 เพิ่มหมวดหมู่สินค้า (Fix 3)
    if ($action === 'add_category') {
        $c_name = trim($_POST['c_name']);
        if (!empty($c_name)) {
            $sql = "INSERT INTO category (c_name) VALUES (?)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $c_name);
                mysqli_stmt_execute($stmt);
                $_SESSION['success_msg'] = "เพิ่มประเภทสินค้าสำเร็จ!";
            }
        }
        header("Location: settings.php?tab=category");
        exit();
    }

    // 🔴 2.5 แก้ไขหมวดหมู่สินค้า (Fix 3)
    if ($action === 'edit_category') {
        $c_id = (int)$_POST['c_id'];
        $c_name = trim($_POST['c_name']);
        if (!empty($c_name)) {
            $sql = "UPDATE category SET c_name = ? WHERE c_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "si", $c_name, $c_id);
                mysqli_stmt_execute($stmt);
                $_SESSION['success_msg'] = "แก้ไขประเภทสินค้าสำเร็จ!";
            }
        }
        header("Location: settings.php?tab=category");
        exit();
    }
}

// 🔴 2.6 ลบหมวดหมู่สินค้า (GET) (Fix 3)
if (isset($_GET['delete_cat'])) {
    $del_id = (int)$_GET['delete_cat'];
    mysqli_query($conn, "DELETE FROM category WHERE c_id = $del_id");
    $_SESSION['success_msg'] = "ลบประเภทสินค้าเรียบร้อยแล้ว!";
    header("Location: settings.php?tab=category");
    exit();
}


// ==========================================
// 3. ดึงข้อมูลแจ้งเตือนสำหรับ Sidebar และข้อมูลหมวดหมู่
// ==========================================
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;
$today = date('Y-m-d');
$newComplaints = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as new_complaints FROM `contact_messages` WHERE DATE(created_at) = '$today'"))['new_complaints'] ?? 0;

// ดึงหมวดหมู่ทั้งหมดมาแสดง
$categories = [];
$resCat = mysqli_query($conn, "SELECT * FROM category ORDER BY c_id DESC");
if ($resCat) {
    while ($c = mysqli_fetch_assoc($resCat)) {
        $categories[] = $c;
    }
}

// ==========================================
// 4. จัดการ Tab ที่ถูกเลือก
// ==========================================
$activeTab = $_GET['tab'] ?? 'store';
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>ตั้งค่าระบบ - Lumina Admin</title>

<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    tailwind.config = {
        darkMode: "class", // 🟢 เปิดใช้ Dark Mode (Fix 2)
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fff5f9", 
                    "background-dark": "#1F1B24", // สีพื้นหลัง Dark Mode
                    "surface-white": "#ffffff",
                    "surface-dark": "#2D2635", // สีการ์ด Dark Mode
                    "text-main": "#1f2937",
                    "text-muted": "#6b7280",
                },
                fontFamily: {
                    sans: ["Prompt", "sans-serif"],
                    display: ["Prompt", "sans-serif"]
                },
                borderRadius: {
                    DEFAULT: "1rem", "lg": "1.5rem", "xl": "2rem", "2xl": "2.5rem"
                },
                boxShadow: {
                    "soft": "0 4px 20px -2px rgba(236, 45, 136, 0.1)",
                    "card": "0 2px 10px -2px rgba(0, 0, 0, 0.05)",
                    "glow": "0 0 15px rgba(236, 45, 136, 0.3)"
                }
            },
        },
    }
</script>
<style>
    body { font-family: 'Prompt', sans-serif; }
    .sidebar-gradient { background: linear-gradient(180deg, #ffffff 0%, #fff5f9 100%); }
    .dark .sidebar-gradient { background: linear-gradient(180deg, #2D2635 0%, #1F1B24 100%); }
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .dark .glass-panel { background: rgba(45, 38, 53, 0.85); border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
    
    .nav-item-active { background-color: #ec2d88; color: white; box-shadow: 0 4px 12px rgba(236, 45, 136, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #ec2d88; }
    .dark .nav-item:hover:not(.nav-item-active) { background-color: rgba(236, 45, 136, 0.2); }
    
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
    .dark ::-webkit-scrollbar-thumb { background: #4B5563; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .setting-menu-item { transition: all 0.3s ease; }
    .setting-menu-item.active { background-color: #fce7f3; color: #ec2d88; border-right: 4px solid #ec2d88; font-weight: 700; }
    .dark .setting-menu-item.active { background-color: rgba(236, 45, 136, 0.2); }
    .setting-menu-item:not(.active):hover { background-color: #f9fafb; color: #ec2d88; }
    .dark .setting-menu-item:not(.active):hover { background-color: #374151; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark font-sans text-text-main dark:text-gray-200 antialiased overflow-x-hidden selection:bg-primary selection:text-white transition-colors duration-300">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-pink-100 dark:border-gray-800 sidebar-gradient p-6 justify-between z-20 shadow-sm transition-colors duration-300">
        <div>
            <a href="../home.php" class="flex items-center gap-2 px-2 mb-10 hover:opacity-80 transition-opacity cursor-pointer">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
                <span class="text-xs font-bold text-purple-500 bg-purple-100 dark:bg-purple-900/30 px-2 py-0.5 rounded-full ml-1 border border-purple-200 dark:border-purple-800/50">Admin</span>
            </a>
            
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="dashboard.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">dashboard</span>
                    <span class="font-medium text-[15px]">ภาพรวมระบบ</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-[15px]">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                        <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    </div>
                    <?php if($newOrders > 0): ?>
                        <span class="bg-primary text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center justify-between px-5 py-3.5 rounded-2xl text-text-muted dark:text-gray-400 transition-all duration-300 group hover:pl-6" href="manage_complaints.php">
                    <div class="flex items-center gap-4">
                        <span class="material-icons-round group-hover:scale-110 transition-transform">forum</span>
                        <span class="font-medium text-[15px]">คำร้องเรียน</span>
                    </div>
                    <?php if($newComplaints > 0): ?>
                        <span class="bg-primary text-white text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $newComplaints ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group mt-2" href="settings.php">
                    <span class="material-icons-round">settings</span>
                    <span class="font-bold text-[15px]">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>
        
        <div class="p-5 rounded-2xl bg-gradient-to-br from-pink-50 to-purple-50 dark:from-gray-800 dark:to-gray-800 border border-pink-100 dark:border-gray-700 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-white dark:bg-gray-700 flex items-center justify-center text-primary shadow-sm">
                <span class="material-icons-round text-xl">support_agent</span>
            </div>
            <div>
                <p class="text-sm font-bold text-primary dark:text-pink-400">ต้องการความช่วยเหลือ?</p>
                <p class="text-xs text-text-muted dark:text-gray-400">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-50 transition-colors duration-300">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main dark:text-white hover:bg-pink-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group invisible"><input type="text"/></div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
                    <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
                    <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
                </button>
                
                <div class="relative group flex items-center">
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full overflow-hidden">
                            <img alt="Admin Profile" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-surface-white dark:bg-surface-dark rounded-3xl shadow-soft border border-pink-100 dark:border-gray-700 overflow-hidden p-5 relative">
                            <div class="text-center mb-4">
                                <span class="text-sm font-bold text-purple-500 bg-purple-50 dark:bg-purple-900/30 px-3 py-1 rounded-full border border-purple-100 dark:border-purple-800/50">
                                    Administrator Mode
                                </span>
                            </div>
                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-16 h-16 overflow-hidden">
                                        <img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mb-6">
                                <h3 class="text-[20px] font-bold text-gray-800 dark:text-white">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3>
                            </div>
                            <div class="flex flex-col gap-3">
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white dark:bg-gray-800 border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[18px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-6 max-w-[1600px] mx-auto w-full">
            
            <div class="mb-4">
                <h1 class="text-3xl font-extrabold text-gray-800 dark:text-white tracking-tight flex items-center gap-3">ตั้งค่าระบบ (Settings)</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">จัดการข้อมูลร้านค้า, หมวดหมู่สินค้า, การชำระเงิน และบัญชีผู้ดูแลระบบ</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-3 bg-surface-white dark:bg-surface-dark rounded-3xl shadow-card border border-pink-50 dark:border-gray-700 overflow-hidden sticky top-28">
                    <div class="flex flex-col">
                        <button onclick="switchTab('store', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 dark:text-gray-300 font-medium <?= $activeTab=='store'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">storefront</span> ข้อมูลร้านค้าทั่วไป
                        </button>
                        <button onclick="switchTab('category', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 dark:text-gray-300 font-medium border-t border-gray-50 dark:border-gray-800 <?= $activeTab=='category'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">category</span> จัดการประเภทสินค้า
                        </button>
                        <button onclick="switchTab('shipping', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 dark:text-gray-300 font-medium border-t border-gray-50 dark:border-gray-800 <?= $activeTab=='shipping'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">local_shipping</span> การจัดส่ง & ชำระเงิน
                        </button>
                        <button onclick="switchTab('admin', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 dark:text-gray-300 font-medium border-t border-gray-50 dark:border-gray-800 <?= $activeTab=='admin'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">admin_panel_settings</span> บัญชีผู้ดูแลระบบ
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-9">
                    
                    <div id="tab-store" class="tab-content bg-surface-white dark:bg-surface-dark rounded-[2rem] shadow-card border border-pink-50 dark:border-gray-700 p-8 <?= $activeTab=='store'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="w-10 h-10 rounded-full bg-pink-50 dark:bg-gray-800 flex items-center justify-center text-primary"><span class="material-icons-round">storefront</span></div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white">ข้อมูลร้านค้า (Store Profile)</h2>
                        </div>
                        <form action="" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_store">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">ชื่อร้านค้า (Store Name)</label><input type="text" name="store_name" value="Lumina Beauty" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all"></div>
                                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">เบอร์โทรศัพท์ติดต่อ</label><input type="text" name="store_phone" value="02-123-4567" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all"></div>
                            </div>
                            <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">อีเมลสำหรับติดต่อลูกค้า</label><input type="email" name="store_email" value="support@luminabeauty.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all"></div>
                            <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">ที่อยู่ร้านค้า</label><textarea name="store_address" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all resize-none">999 อาคารลูมิน่า ชั้น 999 ถนนอวกาศ แขวงดาวเสาร์ เขตทางช้างเผือก จักรวาล หรือ เอกภพ 99999</textarea></div>
                            <div class="text-right pt-4"><button type="submit" class="bg-primary hover:bg-pink-600 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-primary/30 transition-transform transform hover:-translate-y-0.5">บันทึกการเปลี่ยนแปลง</button></div>
                        </form>
                    </div>

                    <div id="tab-category" class="tab-content bg-surface-white dark:bg-surface-dark rounded-[2rem] shadow-card border border-pink-50 dark:border-gray-700 p-8 <?= $activeTab=='category'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="w-10 h-10 rounded-full bg-orange-50 dark:bg-gray-800 flex items-center justify-center text-orange-500"><span class="material-icons-round">category</span></div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white">จัดการประเภทสินค้า (Categories)</h2>
                        </div>
                        
                        <form action="" method="POST" class="flex flex-col sm:flex-row gap-3 mb-8 p-5 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                            <input type="hidden" name="action" value="add_category">
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">เพิ่มหมวดหมู่ใหม่</label>
                                <input type="text" name="c_name" required placeholder="ระบุชื่อหมวดหมู่ (เช่น LIPS)" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="w-full sm:w-auto bg-primary hover:bg-pink-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-md transition-transform transform hover:-translate-y-0.5 flex items-center justify-center gap-1">
                                    <span class="material-icons-round text-[18px]">add</span> เพิ่ม
                                </button>
                            </div>
                        </form>

                        <div class="space-y-3">
                            <?php if(empty($categories)): ?>
                                <p class="text-center text-gray-500 py-4">ยังไม่มีข้อมูลประเภทสินค้า</p>
                            <?php else: ?>
                                <?php foreach($categories as $c): ?>
                                <div class="flex justify-between items-center bg-white dark:bg-surface-dark p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-pink-200 transition-colors">
                                    <span class="font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($c['c_name']) ?></span>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="editCategory(<?= $c['c_id'] ?>, '<?= htmlspecialchars($c['c_name'], ENT_QUOTES) ?>')" class="text-blue-500 hover:text-blue-700 bg-blue-50 dark:bg-gray-800 p-2 rounded-lg transition-colors" title="แก้ไข">
                                            <span class="material-icons-round text-[18px]">edit</span>
                                        </button>
                                        <button type="button" onclick="deleteCategory(<?= $c['c_id'] ?>)" class="text-red-500 hover:text-red-700 bg-red-50 dark:bg-gray-800 p-2 rounded-lg transition-colors" title="ลบ">
                                            <span class="material-icons-round text-[18px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="tab-shipping" class="tab-content bg-surface-white dark:bg-surface-dark rounded-[2rem] shadow-card border border-pink-50 dark:border-gray-700 p-8 <?= $activeTab=='shipping'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-gray-800 flex items-center justify-center text-blue-500"><span class="material-icons-round">local_shipping</span></div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white">เงื่อนไขการจัดส่ง & ชำระเงิน</h2>
                        </div>
                        <form action="" method="POST" class="space-y-8">
                            <input type="hidden" name="action" value="update_shipping">
                            <div>
                                <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">sell</span> ตั้งค่าค่าจัดส่ง</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50/50 dark:bg-gray-800/50 p-5 rounded-2xl border border-gray-100 dark:border-gray-700">
                                    <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">ยอดขั้นต่ำสำหรับส่งฟรี</label><div class="relative"><span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span><input type="number" value="1000" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none font-bold"></div></div>
                                    <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">ค่าส่งแบบธรรมดา</label><div class="relative"><span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span><input type="number" value="50" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none"></div></div>
                                    <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">ค่าส่งด่วน (Express)</label><div class="relative"><span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span><input type="number" value="100" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-primary/30 outline-none"></div></div>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">account_balance</span> บัญชีธนาคารสำหรับรับโอน</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/50 dark:bg-gray-800/50 p-5 rounded-2xl border border-gray-100 dark:border-gray-700">
                                    <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">เลือกธนาคาร</label><select class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white outline-none"><option value="BBL">กรุงเทพ</option><option value="SCB">ไทยพาณิชย์</option></select></div>
                                    <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">เลขบัญชี</label><input type="text" value="414-425-3830" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 dark:text-white font-mono outline-none"></div>
                                </div>
                            </div>
                            <div class="text-right pt-2"><button type="submit" class="bg-primary hover:bg-pink-600 text-white px-8 py-3 rounded-full font-bold shadow-lg transition-transform transform hover:-translate-y-0.5">บันทึกการเปลี่ยนแปลง</button></div>
                        </form>
                    </div>

                    <div id="tab-admin" class="tab-content bg-surface-white dark:bg-surface-dark rounded-[2rem] shadow-card border border-pink-50 dark:border-gray-700 p-8 <?= $activeTab=='admin'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div class="w-10 h-10 rounded-full bg-purple-50 dark:bg-gray-800 flex items-center justify-center text-purple-500"><span class="material-icons-round">admin_panel_settings</span></div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white">จัดการบัญชีผู้ดูแลระบบ</h2>
                        </div>
                        <form action="" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_admin">
                            <div class="bg-purple-50/30 dark:bg-purple-900/10 p-6 rounded-2xl border border-purple-100/50 dark:border-purple-800/30 mb-6 flex items-center gap-4">
                                <img src="<?= $adminAvatar ?>" class="w-16 h-16 rounded-full shadow-sm border-2 border-white dark:border-gray-700">
                                <div><p class="text-xs text-gray-500 dark:text-gray-400 mb-1">ผู้ดูแลระบบปัจจุบัน</p><p class="text-lg font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($adminName) ?></p></div>
                            </div>
                            <div class="max-w-md space-y-6">
                                <div><label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">ชื่อผู้ดูแลระบบ (Username)</label><input type="text" name="admin_username" value="<?= htmlspecialchars($adminName) ?>" required class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-400/30 outline-none"></div>
                                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2"><span class="material-icons-round text-orange-500 text-[18px]">lock</span> เปลี่ยนรหัสผ่าน</h3>
                                    <div class="space-y-4">
                                        <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">รหัสผ่านใหม่ (ปล่อยว่างถ้าไม่เปลี่ยน)</label><input type="password" name="new_password" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none"></div>
                                        <div><label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">ยืนยันรหัสผ่านใหม่</label><input type="password" name="confirm_password" class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 dark:text-white outline-none"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-6"><button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-purple-500/30 transition-transform transform hover:-translate-y-0.5">อัปเดตบัญชี</button></div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // 🟢 ระบบ Dark Mode 🟢
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // ระบบสลับหน้า Tab
    function switchTab(tabId, btnElement) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.setting-menu-item').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
        btnElement.classList.add('active');
        window.history.pushState(null, null, '?tab=' + tabId);
    }

    // 🟢 ฟังก์ชันแก้ไขหมวดหมู่สินค้า 🟢
    function editCategory(id, oldName) {
        Swal.fire({
            title: 'แก้ไขชื่อหมวดหมู่',
            input: 'text',
            inputValue: oldName,
            showCancelButton: true,
            confirmButtonText: 'บันทึกการแก้ไข',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#ec2d88',
            customClass: { popup: 'rounded-3xl dark:bg-gray-800 dark:text-white', confirmButton: 'rounded-full px-6 font-bold', cancelButton: 'rounded-full px-6 font-bold', input: 'rounded-xl dark:bg-gray-700 dark:text-white dark:border-gray-600' }
        }).then((result) => {
            if (result.isConfirmed && result.value.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="action" value="edit_category">
                                  <input type="hidden" name="c_id" value="${id}">
                                  <input type="hidden" name="c_name" value="${result.value.trim()}">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // 🟢 ฟังก์ชันลบหมวดหมู่สินค้า 🟢
    function deleteCategory(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: 'หากลบหมวดหมู่นี้ อาจมีผลกับสินค้าที่เชื่อมโยงอยู่!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl dark:bg-gray-800 dark:text-white', confirmButton: 'rounded-full px-6 font-bold', cancelButton: 'rounded-full px-6 font-bold' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '?delete_cat=' + id;
        });
    }

    // แจ้งเตือน SweetAlert2
    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '<?= $_SESSION['success_msg'] ?>', showConfirmButton: false, timer: 3000, customClass: { popup: 'rounded-2xl dark:bg-gray-800 dark:text-white' }});
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_msg'])): ?>
        Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: '<?= $_SESSION['error_msg'] ?>', showConfirmButton: false, timer: 3000, customClass: { popup: 'rounded-2xl dark:bg-gray-800 dark:text-white' }});
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>
</script>
</body></html>