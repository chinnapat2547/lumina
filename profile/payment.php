<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php';

// ตรวจสอบสถานะการล็อกอิน
if (!isset($_SESSION['u_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$isLoggedIn = true;
$u_id = $_SESSION['u_id'];
$profileImage = "https://ui-avatars.com/api/?name=User&background=F43F85&color=fff";
$success_msg = '';
$error_msg = '';

// ==========================================
// 1. จัดการการเพิ่มบัตรเครดิต (Insert)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_card') {
    // ลบช่องว่างออกจากเลขบัตร
    $cardNumber = str_replace(' ', '', $_POST['cardNumber']);
    $cardName = strtoupper(trim($_POST['cardName']));
    $cardExpiry = trim($_POST['cardExpiry']);
    
    // ดึง 4 ตัวท้าย
    $last4 = substr($cardNumber, -4);
    // เช็คประเภทบัตร (ขึ้นต้นด้วย 4 = VISA, นอกนั้นสมมติเป็น MASTERCARD)
    $cardType = (substr($cardNumber, 0, 1) === '4') ? 'VISA' : 'MASTERCARD';

    // บันทึกลงฐานข้อมูล (ไม่บันทึก CVV เด็ดขาดเพื่อความปลอดภัย)
    $insertSql = "INSERT INTO `payment` (u_id, card_type, card_last4, card_name, expiry_date) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = mysqli_prepare($conn, $insertSql)) {
        mysqli_stmt_bind_param($stmt, "issss", $u_id, $cardType, $last4, $cardName, $cardExpiry);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "เพิ่มบัตรลงท้ายด้วย $last4 สำเร็จ!";
        } else {
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกบัตร";
        }
        mysqli_stmt_close($stmt);
    }
}

// ==========================================
// 2. จัดการการลบบัตร (Delete)
// ==========================================
if (isset($_GET['delete_card'])) {
    $delete_id = $_GET['delete_card'];
    $delSql = "DELETE FROM `payment` WHERE card_id = ? AND u_id = ?";
    if ($stmt = mysqli_prepare($conn, $delSql)) {
        mysqli_stmt_bind_param($stmt, "ii", $delete_id, $u_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: payment.php");
        exit();
    }
}

// ==========================================
// 3. ดึงข้อมูลบัตรที่บันทึกไว้ของ User นี้
// ==========================================
$savedCards = [];
$sqlCards = "SELECT * FROM `payment` WHERE u_id = ? ORDER BY created_at DESC";
if ($stmt = mysqli_prepare($conn, $sqlCards)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $resultCards = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($resultCards)) {
        $savedCards[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ==========================================
// 4. ดึงข้อมูล User (Profile Image)
// ==========================================
$sql = "SELECT a.u_username, a.u_email, u.u_image 
        FROM `account` a 
        LEFT JOIN `user` u ON a.u_id = u.u_id 
        WHERE a.u_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($userData = mysqli_fetch_assoc($result)) {
        if (!empty($userData['u_image']) && file_exists("uploads/" . $userData['u_image'])) {
            $profileImage = "uploads/" . $userData['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
        }
    }
    mysqli_stmt_close($stmt);
}

// กำหนดค่าเริ่มต้นเป็น 0 เสมอ
    $totalCartItems = 0; 
    
    // ดึงจำนวนสินค้าล่าสุดในตะกร้าแบบ Real-time
    $sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
    if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
        mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
        mysqli_stmt_execute($stmtCartCount);
        $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
        if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
            // สำคัญมาก: ถ้าตะกร้าว่าง SUM() จะได้ค่า NULL ต้องดักให้เป็น 0
            $totalCartItems = ($rowCartCount['total_qty'] !== null) ? (int)$rowCartCount['total_qty'] : 0;
        }
        mysqli_stmt_close($stmtCartCount);
    }
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>วิธีการชำระเงิน - Lumina Beauty</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
              "card-light": "#FFFFFF",
              "card-dark": "#2D2635",
              "text-light": "#374151",
              "text-dark": "#E5E7EB",
            },
            fontFamily: {
              display: ["Prompt", "sans-serif"],
              body: ["Prompt", "sans-serif"],
            },
            borderRadius: {
              DEFAULT: "1.5rem",
              'xl': '1rem',
              '2xl': '1.5rem',
              '3xl': '2rem',
            },
            boxShadow: {
                'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
            },
            animation: {
                'float': 'float 6s ease-in-out infinite',
                'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-20px)' },
                }
            }
          },
        },
      };
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
        .card-gradient-1 { background: linear-gradient(135deg, #F43F85 0%, #A78BFA 100%); }
        .card-gradient-2 { background: linear-gradient(135deg, #7BD3EA 0%, #9B86BD 100%); }
        .card-gradient-3 { background: linear-gradient(135deg, #10B981 0%, #3B82F6 100%); }
        
        .form-input {
            width: 100%;
            border-radius: 1rem;
            border: 1px solid #FBCFE8;
            background-color: #FFF5F7;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            color: #374151;
            transition: all 0.3s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: #F43F85;
            box-shadow: 0 0 0 3px rgba(244, 63, 133, 0.2);
            background-color: #FFFFFF;
        }
        .dark .form-input {
            border-color: #4B5563;
            background-color: #1F2937;
            color: #E5E7EB;
        }
        .dark .form-input:focus {
            border-color: #F43F85;
            background-color: #374151;
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-pink-200 dark:bg-pink-900 blur-3xl opacity-30 animate-pulse"></div>
    <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-3xl opacity-30"></div>
</div>

<nav class="sticky top-0 z-50 glass-panel shadow-sm px-6 py-4 mb-8 relative z-50">
<div class="max-w-7xl mx-auto flex justify-between items-center">
<a href="../home.php" class="flex items-center space-x-2 cursor-pointer hover:opacity-80 transition-opacity">
    <span class="material-icons-round text-primary text-4xl">spa</span>
    <span class="font-bold text-2xl tracking-tight text-primary">Lumina</span>
</a>
<div class="flex items-center space-x-2 sm:space-x-2">
    <a href="../shop/cart.php" class="hover:text-primary transition relative flex items-center">
                <span class="material-icons-round text-2xl">shopping_bag</span>
                <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800">
                    <?= isset($totalCartItems) ? $totalCartItems : 0 ?>
                </span>
            </a>
    <button class="w-10 h-10 flex items-center justify-center text-gray-500 dark:text-gray-300 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-800 rounded-full transition-all" onclick="toggleTheme()">
        <span class="material-icons-round dark:hidden text-2xl">dark_mode</span>
        <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
    </button>
    <a href="account.php" class="block w-10 h-10 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-0.5 shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer">
        <div class="bg-white dark:bg-gray-800 rounded-full p-[2px] w-full h-full">
            <img alt="Profile" class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($profileImage) ?>"/>
        </div>
    </a>
</div>
</div>
</div>
</div>
</div>
</nav>

<main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    <div class="flex flex-col lg:flex-row gap-8">
        
        <aside class="w-full lg:w-1/4">
            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft sticky top-28 border border-transparent dark:border-gray-700">
                <div class="flex flex-col space-y-2">
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="account.php">
                        <span class="material-icons-round">person</span>
                        <span>ข้อมูลส่วนตัว</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="manageaccount.php">
                        <span class="material-icons-round">manage_accounts</span>
                        <span>รายละเอียดบัญชี</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 bg-pink-50 dark:bg-pink-900/20 text-primary font-medium rounded-2xl transition-all shadow-sm" href="payment.php">
                        <span class="material-icons-round">credit_card</span>
                        <span>วิธีการชำระเงิน</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="orders.php">
                        <span class="material-icons-round">history</span>
                        <span>ประวัติการสั่งซื้อ</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="address.php">
                        <span class="material-icons-round">location_on</span>
                        <span>ที่อยู่จัดส่ง</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="../shop/favorites.php">
                        <span class="material-icons-round">favorite</span>
                        <span>สิ่งที่ถูกใจ</span>
                    </a>
                    <div class="border-t border-gray-100 dark:border-gray-700 my-2 pt-2"></div>
                    <a class="flex items-center space-x-3 px-4 py-3 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-2xl transition-all" href="../auth/logout.php">
                        <span class="material-icons-round">logout</span>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </aside>

        <section class="w-full lg:w-3/4 space-y-6">
            
            <div class="bg-gradient-to-r from-pink-400 to-purple-400 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg">
                <div class="relative z-10 flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                        <span class="material-icons-round text-4xl">credit_card</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">วิธีการชำระเงิน</h1>
                        <p class="text-pink-100 text-sm opacity-90">จัดการข้อมูลบัตรและช่องทางชำระเงินของคุณให้ปลอดภัย</p>
                    </div>
                </div>
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
            </div>

            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-8 shadow-soft border border-transparent dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2 mb-6 flex items-center gap-2">
                    <span class="material-icons-round text-primary">payment</span> บัตรที่บันทึกไว้
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <?php 
                    $colorIndex = 1;
                    foreach ($savedCards as $card): 
                        // สลับสีพื้นหลังการ์ดไปเรื่อยๆ (1, 2, 3)
                        $gradientClass = "card-gradient-" . $colorIndex;
                        $colorIndex = $colorIndex >= 3 ? 1 : $colorIndex + 1;
                    ?>
                        <div class="group relative animate-fade-in-up">
                            <div class="<?= $gradientClass ?> h-48 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden flex flex-col justify-between transform transition-transform group-hover:scale-[1.02]">
                                <div class="absolute top-0 right-0 p-4 opacity-20">
                                    <span class="material-icons-round text-6xl">spa</span>
                                </div>
                                <div class="flex justify-between items-start z-10">
                                    <span class="material-icons-round text-4xl">contactless</span>
                                    <span class="font-bold tracking-widest text-lg italic"><?= htmlspecialchars($card['card_type']) ?></span>
                                </div>
                                <div class="z-10">
                                    <div class="text-xl font-mono tracking-widest mb-1 shadow-sm">•••• •••• •••• <?= htmlspecialchars($card['card_last4']) ?></div>
                                    <div class="flex justify-between text-xs opacity-80 uppercase tracking-tighter mt-2">
                                        <div>Card Holder</div>
                                        <div>Expires</div>
                                    </div>
                                    <div class="flex justify-between font-medium">
                                        <div class="truncate max-w-[150px]"><?= htmlspecialchars($card['card_name']) ?></div>
                                        <div><?= htmlspecialchars($card['expiry_date']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-3">
                                <a href="#" onclick="confirmDelete(<?= $card['card_id'] ?>, event)" class="ml-auto px-5 py-2 text-xs font-semibold text-red-500 bg-red-50 dark:bg-red-900/20 rounded-xl hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors shadow-sm text-center flex items-center justify-center">ลบ</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <button onclick="openModal('addCardModal')" class="h-48 rounded-3xl border-2 border-dashed border-pink-300 dark:border-gray-600 flex flex-col items-center justify-center gap-3 hover:border-primary dark:hover:border-primary hover:bg-pink-50 dark:hover:bg-gray-800 transition-all group bg-white/50 dark:bg-card-dark">
                        <div class="w-12 h-12 rounded-full bg-pink-100 dark:bg-gray-700 flex items-center justify-center group-hover:scale-110 group-hover:bg-primary transition-all shadow-sm">
                            <span class="material-icons-round text-primary group-hover:text-white text-3xl">add</span>
                        </div>
                        <span class="font-bold text-gray-500 dark:text-gray-400 group-hover:text-primary">เพิ่มบัตรใหม่</span>
                    </button>

                </div>
            </div>

            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-8 shadow-soft border border-transparent dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2 mb-6 flex items-center gap-2">
                    <span class="material-icons-round text-primary">account_balance</span> วิธีการชำระเงินอื่นๆ
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div onclick="openModal('promptPayModal')" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl border border-transparent hover:border-primary/50 transition-all cursor-pointer">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-[#1b3d6b] rounded-xl flex items-center justify-center text-white font-bold text-[10px] p-1 text-center shadow-md">
                                PROMPT<br>PAY
                            </div>
                            <div>
                                <h3 class="font-bold dark:text-white leading-tight text-md">พร้อมเพย์</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">สแกน QR Code (แนะนำ)</p>
                            </div>
                        </div>
                        <span class="material-icons-round text-gray-400">qr_code_scanner</span>
                    </div>
                    
                    <div onclick="openModal('bankTransferModal')" class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl border border-transparent hover:border-primary/50 transition-all cursor-pointer">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center shadow-sm">
                                <span class="material-icons-round text-purple-600 dark:text-purple-400 text-2xl">account_balance</span>
                            </div>
                            <div>
                                <h3 class="font-bold dark:text-white leading-tight text-md">โอนผ่านธนาคาร</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">อัปโหลดสลิปโอนเงิน</p>
                            </div>
                        </div>
                        <span class="material-icons-round text-gray-400">payments</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 bg-blue-50 dark:bg-gray-800 p-5 rounded-2xl text-sm text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-gray-700 mt-6">
                <span class="material-icons-round text-blue-500 text-2xl">lock</span>
                <p>ข้อมูลบัตรเครดิตของคุณถูกเข้ารหัสและรักษาความปลอดภัยด้วยมาตรฐานสากล เราไม่มีการเก็บเลขรหัส CVV ของคุณไว้ในระบบ</p>
            </div>

        </section>
    </div>
</main>

<div id="addCardModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-3xl p-8 w-full max-w-md shadow-2xl transform scale-95 transition-transform duration-300 modal-content">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-display font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="material-icons-round text-primary">add_card</span> เพิ่มบัตรใหม่
            </h2>
            <button onclick="closeModal('addCardModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                <span class="material-icons-round text-3xl">close</span>
            </button>
        </div>

        <form action="payment.php" method="POST">
            <input type="hidden" name="action" value="add_card">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 ml-1">หมายเลขบัตร (Card Number)</label>
                    <input type="text" name="cardNumber" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required class="form-input font-mono">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 ml-1">ชื่อบนบัตร (Cardholder Name)</label>
                    <input type="text" name="cardName" placeholder="NAME LASTNAME" required class="form-input uppercase">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 ml-1">วันหมดอายุ</label>
                        <input type="text" name="cardExpiry" id="cardExpiry" placeholder="MM/YY" maxlength="5" required class="form-input font-mono text-center">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 ml-1">รหัส CVV</label>
                        <input type="password" placeholder="•••" maxlength="3" required class="form-input font-mono tracking-widest text-center">
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full mt-8 bg-primary hover:bg-pink-600 text-white font-bold py-3.5 rounded-full shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center justify-center gap-2">
                <span class="material-icons-round">save</span> บันทึกบัตร
            </button>
        </form>
    </div>
</div>

<div id="promptPayModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-3xl p-8 w-full max-w-sm shadow-2xl transform scale-95 transition-transform duration-300 modal-content text-center">
        <div class="flex justify-end mb-2">
            <button onclick="closeModal('promptPayModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                <span class="material-icons-round text-3xl">close</span>
            </button>
        </div>
        <div class="w-16 h-16 bg-[#1b3d6b] rounded-2xl flex items-center justify-center text-white font-bold text-sm mx-auto mb-4 shadow-lg">
            PROMPT<br>PAY
        </div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">สแกน QR Code</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">บันทึกรูปภาพหรือสแกนเพื่อชำระเงิน</p>
        
        <div class="bg-gray-100 p-4 rounded-2xl inline-block mb-6 shadow-inner">
            <img src="qr.JPEG" alt="PromptPay QR Code" class="w-48 h-48 object-cover rounded-xl" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/d/d0/QR_code_for_mobile_English_Wikipedia.svg'">
        </div>
        <div class="mb-4">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ชื่อบัญชี</p>
            <p class="font-bold text-lg text-gray-800 dark:text-white">ชื่อบัญชี: บจก. ลูมิน่า บิวตี้</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">พร้อมเพย์</p>
            <p class="font-mono font-bold text-2xl text-primary tracking-widest">061-132-3746</p>
        </div>

        <button onclick="closeModal('promptPayModal')" class="w-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-3.5 rounded-full transition">
            ปิดหน้าต่าง
        </button>
    </div>
</div>

<div id="bankTransferModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-3xl p-8 w-full max-w-md shadow-2xl transform scale-95 transition-transform duration-300 modal-content text-center">
        <div class="flex justify-end mb-2">
            <button onclick="closeModal('bankTransferModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                <span class="material-icons-round text-3xl">close</span>
            </button>
        </div>
        <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm text-purple-600 dark:text-purple-400">
            <span class="material-icons-round text-4xl">account_balance</span>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">โอนผ่านธนาคาร</h2>
        <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">กรุณาโอนเงินมาที่บัญชีด้านล่างนี้</p>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-6 mb-6 text-left border border-gray-100 dark:border-gray-700">
            <div class="mb-4">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ธนาคาร</p>
                <p class="font-bold text-lg text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="w-4 h-4 rounded-full bg-[#1e4598] inline-block"></span> ธนาคารกรุงเทพ (BBL)
                </p>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">ชื่อบัญชี</p>
                <p class="font-bold text-lg text-gray-800 dark:text-white">นายชินพัฒน์ ลิ่มดิลกธรรม</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">เลขที่บัญชี</p>
                <p class="font-mono font-bold text-2xl text-primary tracking-widest">414-4-25383-0</p>
            </div>
        </div>

        <button onclick="closeModal('bankTransferModal')" class="w-full bg-primary hover:bg-pink-600 text-white font-bold py-3.5 rounded-full transition shadow-md">
            รับทราบ
        </button>
    </div>
</div>

<script>
    // สลับ Dark Mode
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // ฟังก์ชันเปิด-ปิด Modal แบบยืดหยุ่น
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return; // ป้องกัน Error หากหา Modal ไม่เจอ
        const content = modal.querySelector('.modal-content');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            if(content) content.classList.remove('scale-95');
        }, 10);
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return; // ป้องกัน Error
        const content = modal.querySelector('.modal-content');
        modal.classList.add('opacity-0');
        if(content) content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // 🟢 ฟังก์ชันแจ้งเตือนยืนยันการลบบัตรด้วย SweetAlert2 (ต้องอยู่นอก DOMContentLoaded เพื่อให้ปุ่ม HTML เรียกใช้งานได้)
    function confirmDelete(cardId, event) {
        event.preventDefault(); // ป้องกันไม่ให้ลิงก์ทำงานทันที
        
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: "หากลบแล้วจะไม่สามารถกู้คืนข้อมูลบัตรได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F43F85', // สีชมพูของเว็บ
            cancelButtonColor: '#9CA3AF',  // สีเทา
            confirmButtonText: 'ตกลง',
            cancelButtonText: 'ยกเลิก',
            customClass: { 
                popup: 'rounded-3xl', // ขอบมนเข้ากับธีม
                confirmButton: 'rounded-full px-6 font-medium',
                cancelButton: 'rounded-full px-6 font-medium'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // ถ้ากด "ใช่, ลบเลย!" ค่อยให้หน้าเว็บวิ่งไปลบข้อมูลที่ PHP
                window.location.href = '?delete_card=' + cardId;
            }
        });
    }

    // 🟢 ทำงานเมื่อโหลดหน้าเว็บเสร็จสมบูรณ์ 100%
    document.addEventListener("DOMContentLoaded", function() {
        
        // จัด Format ข้อมูลในช่องกรอกบัตรอัตโนมัติ (ใส่ไว้ในนี้จะปลอดภัยจาก Error)
        const cardNumberInput = document.getElementById('cardNumber');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(.{4})/g, '$1 ').trim();
                e.target.value = value;
            });
        }

        const cardExpiryInput = document.getElementById('cardExpiry');
        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) {
                    value = value.slice(0, 2) + '/' + value.slice(2, 4);
                }
                e.target.value = value;
            });
        }

        // แจ้งเตือน SweetAlert2 เมื่ออัปเดต PHP เสร็จ (เพิ่ม ENT_QUOTES เพื่อป้องกัน Error จากเครื่องหมายคำพูด)
        <?php if (!empty($success_msg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>',
                confirmButtonColor: '#F43F85',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6' }
            });
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '<?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?>',
                confirmButtonColor: '#F43F85',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6' }
            });
        <?php endif; ?>
    });
</script>

</body></html>