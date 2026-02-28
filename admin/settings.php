<?php
session_start();
require_once '../config/connectdbuser.php';

// ==========================================
// 1. ตรวจสอบสิทธิ์ (จำลอง Admin)
// ==========================================
// if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login.php"); exit(); }
$admin_id = $_SESSION['admin_id'] ?? 1; // สมมติว่าดึงจาก Session หรือกำหนดค่าชั่วคราว
$adminName = $_SESSION['admin_username'] ?? 'Admin Nina'; 
$adminAvatar = "https://ui-avatars.com/api/?name=Admin&background=a855f7&color=fff";

// ==========================================
// 2. จัดการบันทึกข้อมูล (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 🔴 2.1 อัปเดตข้อมูลผู้ดูแลระบบ (ใช้ฐานข้อมูลจริงตาราง adminaccount)
    if ($action === 'update_admin') {
        $new_username = mysqli_real_escape_string($conn, trim($_POST['admin_username']));
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                // เข้ารหัสรหัสผ่านใหม่
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sqlUpAdmin = "UPDATE `adminaccount` SET admin_username = ?, admin_password = ? WHERE admin_id = ?";
                if ($stmt = mysqli_prepare($conn, $sqlUpAdmin)) {
                    mysqli_stmt_bind_param($stmt, "ssi", $new_username, $hashed_password, $admin_id);
                    mysqli_stmt_execute($stmt);
                    $_SESSION['admin_username'] = $new_username; // อัปเดต Session
                    $_SESSION['success_msg'] = "อัปเดตข้อมูลบัญชีผู้ดูแลระบบสำเร็จ!";
                }
            } else {
                $_SESSION['error_msg'] = "รหัสผ่านใหม่ไม่ตรงกัน!";
            }
        } else {
            // อัปเดตแค่ชื่อ
            $sqlUpAdmin = "UPDATE `adminaccount` SET admin_username = ? WHERE admin_id = ?";
            if ($stmt = mysqli_prepare($conn, $sqlUpAdmin)) {
                mysqli_stmt_bind_param($stmt, "si", $new_username, $admin_id);
                mysqli_stmt_execute($stmt);
                $_SESSION['admin_username'] = $new_username; // อัปเดต Session
                $_SESSION['success_msg'] = "อัปเดตชื่อผู้ดูแลระบบสำเร็จ!";
            }
        }
        header("Location: settings.php?tab=admin");
        exit();
    }
    
    // 🔴 2.2 อัปเดตข้อมูลร้านค้า (จำลองการทำงาน รอมีตาราง settings)
    if ($action === 'update_store') {
        // อนาคตสามารถนำตัวแปรเหล่านี้ไป INSERT/UPDATE ลงตาราง settings ได้
        $store_name = $_POST['store_name'];
        $store_phone = $_POST['store_phone'];
        $_SESSION['success_msg'] = "บันทึกข้อมูลร้านค้าเรียบร้อยแล้ว!";
        header("Location: settings.php?tab=store");
        exit();
    }

    // 🔴 2.3 อัปเดตค่าจัดส่งและการชำระเงิน (จำลองการทำงาน)
    if ($action === 'update_shipping') {
        $_SESSION['success_msg'] = "อัปเดตเงื่อนไขการจัดส่งและการชำระเงินเรียบร้อยแล้ว!";
        header("Location: settings.php?tab=shipping");
        exit();
    }
}

// ==========================================
// 3. ดึงข้อมูลแจ้งเตือนสำหรับ Sidebar
// ==========================================
$countPending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM `orders` WHERE status='pending'"))['c'] ?? 0;

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
        theme: {
            extend: {
                colors: {
                    primary: "#ec2d88", 
                    "primary-light": "#fce7f3",
                    secondary: "#a855f7",
                    "background-light": "#fff5f9", 
                    "surface-white": "#ffffff",
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
    .glass-panel { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(236, 45, 136, 0.1); }
    .nav-item-active { background-color: #ec2d88; color: white; box-shadow: 0 4px 12px rgba(236, 45, 136, 0.3); }
    .nav-item:hover:not(.nav-item-active) { background-color: #fce7f3; color: #ec2d88; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: #ec2d88; }
    
    /* ซ่อน/แสดงเนื้อหา Tab */
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    /* สไตล์ปุ่มเมนูตั้งค่าฝั่งซ้าย */
    .setting-menu-item { transition: all 0.3s ease; }
    .setting-menu-item.active { background-color: #fce7f3; color: #ec2d88; border-right: 4px solid #ec2d88; font-weight: 700; }
    .setting-menu-item:not(.active):hover { background-color: #f9fafb; color: #ec2d88; }
</style>
</head>
<body class="bg-background-light font-sans text-text-main antialiased overflow-x-hidden selection:bg-primary selection:text-white">
<div class="flex min-h-screen w-full">
    
    <aside class="hidden lg:flex flex-col w-72 h-screen sticky top-0 border-r border-pink-100 sidebar-gradient p-6 justify-between z-20 shadow-sm">
        <div>
            <a href="../home.php" class="flex items-center gap-2 px-2 mb-10 hover:opacity-80 transition-opacity cursor-pointer">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
                <span class="text-xs font-bold text-purple-500 bg-purple-100 px-2 py-0.5 rounded-full ml-1">Admin</span>
            </a>
            <nav class="flex flex-col gap-2">
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="dashboard.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">dashboard</span>
                    <span class="font-medium text-[15px]">ภาพรวมระบบ</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_products.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">inventory_2</span>
                    <span class="font-medium text-[15px]">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                    <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    <?php if($countPending > 0): ?>
                        <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $countPending ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_customers.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="settings.php">
                    <span class="material-icons-round">settings</span>
                    <span class="font-bold text-[15px]">ตั้งค่าระบบ</span>
                </a>
            </nav>
        </div>
        <div class="p-5 rounded-2xl bg-gradient-to-br from-pink-50 to-purple-50 border border-pink-100 flex items-center gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-primary shadow-sm">
                <span class="material-icons-round text-xl">support_agent</span>
            </div>
            <div>
                <p class="text-sm font-bold text-primary">ต้องการความช่วยเหลือ?</p>
                <p class="text-xs text-text-muted">ติดต่อทีมพัฒนาระบบ</p>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0">
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-10">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main hover:bg-pink-50 rounded-xl transition-colors"><span class="material-icons-round">menu</span></button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group invisible"> <input type="text"/>
            </div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <div class="relative group flex items-center">
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white rounded-full p-[2px] w-full h-full">
                            <img alt="Admin" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-white rounded-3xl shadow-soft border border-pink-100 overflow-hidden p-5 relative">
                            <div class="text-center mb-4"><span class="text-sm font-bold text-purple-500 bg-purple-50 px-3 py-1 rounded-full">Administrator Mode</span></div>
                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white rounded-full p-[2px] w-16 h-16"><img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover"></div>
                                </div>
                            </div>
                            <div class="text-center mb-6"><h3 class="text-[20px] font-bold text-gray-800">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3></div>
                            <div class="flex flex-col gap-3">
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
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
                <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight flex items-center gap-3">
                    ตั้งค่าระบบ (Settings)
                </h1>
                <p class="text-sm text-gray-500 mt-1">จัดการข้อมูลร้านค้า, การชำระเงิน, และบัญชีผู้ดูแลระบบ</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-3 bg-white rounded-3xl shadow-card border border-pink-50 overflow-hidden sticky top-28">
                    <div class="flex flex-col">
                        <button onclick="switchTab('store', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 font-medium <?= $activeTab=='store'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">storefront</span> ข้อมูลร้านค้าทั่วไป
                        </button>
                        <button onclick="switchTab('shipping', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 font-medium border-t border-gray-50 <?= $activeTab=='shipping'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">local_shipping</span> การจัดส่ง & ชำระเงิน
                        </button>
                        <button onclick="switchTab('admin', this)" class="setting-menu-item flex items-center gap-3 px-6 py-4 text-gray-600 font-medium border-t border-gray-50 <?= $activeTab=='admin'?'active':'' ?>">
                            <span class="material-icons-round text-[20px]">admin_panel_settings</span> บัญชีผู้ดูแลระบบ
                        </button>
                    </div>
                </div>

                <div class="lg:col-span-9">
                    
                    <div id="tab-store" class="tab-content bg-white rounded-[2rem] shadow-card border border-pink-50 p-8 <?= $activeTab=='store'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-pink-50 flex items-center justify-center text-primary"><span class="material-icons-round">storefront</span></div>
                            <h2 class="text-xl font-bold text-gray-800">ข้อมูลร้านค้า (Store Profile)</h2>
                        </div>
                        
                        <form action="" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_store">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อร้านค้า (Store Name)</label>
                                    <input type="text" name="store_name" value="Lumina Beauty" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">เบอร์โทรศัพท์ติดต่อ</label>
                                    <input type="text" name="store_phone" value="081-234-5678" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">อีเมลสำหรับติดต่อลูกค้า</label>
                                <input type="email" name="store_email" value="support@luminabeauty.com" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">ที่อยู่ร้านค้า (สำหรับแสดงในใบเสร็จ)</label>
                                <textarea name="store_address" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all resize-none">123 หมู่ 4 ถนนความสวย แขวงน่ารัก เขตสดใส กรุงเทพมหานคร 10110</textarea>
                            </div>

                            <div class="flex items-center gap-3 bg-blue-50 text-blue-600 p-4 rounded-xl border border-blue-100 text-sm font-medium">
                                <span class="material-icons-round">info</span>
                                ข้อมูลส่วนนี้จะถูกนำไปแสดงในหน้าเว็บฝั่งลูกค้าและในใบเสร็จรับเงิน
                            </div>

                            <div class="text-right pt-4">
                                <button type="submit" class="bg-primary hover:bg-pink-600 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-primary/30 transition-transform transform hover:-translate-y-0.5">บันทึกการเปลี่ยนแปลง</button>
                            </div>
                        </form>
                    </div>

                    <div id="tab-shipping" class="tab-content bg-white rounded-[2rem] shadow-card border border-pink-50 p-8 <?= $activeTab=='shipping'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500"><span class="material-icons-round">local_shipping</span></div>
                            <h2 class="text-xl font-bold text-gray-800">เงื่อนไขการจัดส่ง & ชำระเงิน</h2>
                        </div>
                        
                        <form action="" method="POST" class="space-y-8">
                            <input type="hidden" name="action" value="update_shipping">
                            
                            <div>
                                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">sell</span> ตั้งค่าค่าจัดส่ง</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50/50 p-5 rounded-2xl border border-gray-100">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ยอดขั้นต่ำสำหรับส่งฟรี</label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span>
                                            <input type="number" value="1000" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none text-gray-800 font-bold">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ค่าส่งแบบธรรมดา (ถ้าไม่ถึงเกณฑ์)</label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span>
                                            <input type="number" value="50" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ค่าส่งด่วน (Express)</label>
                                        <div class="relative">
                                            <span class="absolute left-4 top-2.5 text-gray-400 font-bold">฿</span>
                                            <input type="number" value="100" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-icons-round text-primary text-[18px]">account_balance</span> บัญชีธนาคารสำหรับรับโอน</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/50 p-5 rounded-2xl border border-gray-100">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">เลือกธนาคาร</label>
                                        <select class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                                            <option value="kbank" selected>ธนาคารกสิกรไทย (KBank)</option>
                                            <option value="scb">ธนาคารไทยพาณิชย์ (SCB)</option>
                                            <option value="bbl">ธนาคารกรุงเทพ (BBL)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 mb-1">เลขบัญชีธนาคาร</label>
                                        <input type="text" value="123-4-56789-0" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none font-mono">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-bold text-gray-500 mb-1">ชื่อบัญชี</label>
                                        <input type="text" value="บริษัท ลูมิน่า บิวตี้ จำกัด" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white focus:ring-2 focus:ring-primary/30 outline-none">
                                    </div>
                                </div>
                            </div>

                            <div class="text-right pt-2">
                                <button type="submit" class="bg-primary hover:bg-pink-600 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-primary/30 transition-transform transform hover:-translate-y-0.5">บันทึกการเปลี่ยนแปลง</button>
                            </div>
                        </form>
                    </div>

                    <div id="tab-admin" class="tab-content bg-white rounded-[2rem] shadow-card border border-pink-50 p-8 <?= $activeTab=='admin'?'active':'' ?>">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-500"><span class="material-icons-round">admin_panel_settings</span></div>
                            <h2 class="text-xl font-bold text-gray-800">จัดการบัญชีผู้ดูแลระบบ</h2>
                        </div>
                        
                        <form action="" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_admin">
                            
                            <div class="bg-purple-50/30 p-6 rounded-2xl border border-purple-100/50 mb-6 flex items-center gap-4">
                                <img src="<?= $adminAvatar ?>" class="w-16 h-16 rounded-full shadow-sm border-2 border-white">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">ผู้ดูแลระบบปัจจุบัน</p>
                                    <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($adminName) ?></p>
                                </div>
                            </div>

                            <div class="max-w-md space-y-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">ชื่อผู้ดูแลระบบ (Username)</label>
                                    <input type="text" name="admin_username" value="<?= htmlspecialchars($adminName) ?>" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-400/30 outline-none transition-all">
                                </div>
                                
                                <div class="pt-4 border-t border-gray-100">
                                    <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-icons-round text-orange-500 text-[18px]">lock</span> เปลี่ยนรหัสผ่าน (ปล่อยว่างถ้าไม่ต้องการเปลี่ยน)</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 mb-1">รหัสผ่านใหม่</label>
                                            <input type="password" name="new_password" placeholder="ตั้งรหัสผ่านใหม่" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-400/30 outline-none transition-all">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 mb-1">ยืนยันรหัสผ่านใหม่</label>
                                            <input type="password" name="confirm_password" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-400/30 outline-none transition-all">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6">
                                <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-8 py-3 rounded-full font-bold shadow-lg shadow-purple-500/30 transition-transform transform hover:-translate-y-0.5">อัปเดตบัญชี</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </main>
</div>

<script>
    // สลับ Tab เมนูตั้งค่า
    function switchTab(tabId, btnElement) {
        // ซ่อนเนื้อหาทั้งหมด
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // รีเซ็ตปุ่มเมนูฝั่งซ้ายทั้งหมด
        document.querySelectorAll('.setting-menu-item').forEach(btn => {
            btn.classList.remove('active');
        });

        // แสดงเนื้อหาที่เลือก
        document.getElementById('tab-' + tabId).classList.add('active');
        
        // เน้นปุ่มที่ถูกคลิก
        btnElement.classList.add('active');

        // อัปเดต URL เพื่อให้เวลารีเฟรชกลับมาหน้าเดิม
        window.history.pushState(null, null, '?tab=' + tabId);
    }

    // 🟢 ระบบแจ้งเตือน (ดึงจาก Session PHP) 🟢
    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ 
            toast: true, position: 'top-end', icon: 'success', 
            title: '<?= $_SESSION['success_msg'] ?>', 
            showConfirmButton: false, timer: 3000,
            customClass: { popup: 'rounded-2xl' }
        });
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        Swal.fire({ 
            toast: true, position: 'top-end', icon: 'error', 
            title: '<?= $_SESSION['error_msg'] ?>', 
            showConfirmButton: false, timer: 3000,
            customClass: { popup: 'rounded-2xl' }
        });
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>