<?php
session_start();
require_once '../config/connectdbuser.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['u_id'])) {
    header("Location: login.php");
    exit();
}

$u_id = $_SESSION['u_id'];
$success_msg = '';
$error_msg = '';

// ==========================================
// 1. ดึงข้อมูลบัญชีปัจจุบันและรูปโปรไฟล์มาแสดง
// ==========================================
// 📌 แก้ไข: ใช้ LEFT JOIN ดึง u_image จากตาราง user เพิ่มเข้ามา
$sql = "SELECT a.u_username, a.u_email, a.u_password, u.u_image 
        FROM `account` a 
        LEFT JOIN `user` u ON a.u_id = u.u_id 
        WHERE a.u_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $accountData = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// 📌 ตรวจสอบและตั้งค่ารูปโปรไฟล์สำหรับแสดงผลที่ Navbar
$displayName = $accountData['u_username'] ?? 'ผู้ใช้งาน';
$profileImage = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=F43F85&color=fff";

if (!empty($accountData['u_image']) && file_exists("uploads/" . $accountData['u_image'])) {
    $profileImage = "uploads/" . $accountData['u_image'];
}

// ==========================================
// 2. จัดการเมื่อมีการกดปุ่ม "บันทึกข้อมูล"
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = trim($_POST['u_username']);
    $new_email = trim($_POST['u_email']);
    
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // เช็คว่า Username หรือ Email ซ้ำกับคนอื่นไหม (ยกเว้นของตัวเอง)
    $checkSql = "SELECT u_id FROM `account` WHERE (u_username = ? OR u_email = ?) AND u_id != ?";
    if ($checkStmt = mysqli_prepare($conn, $checkSql)) {
        mysqli_stmt_bind_param($checkStmt, "ssi", $new_username, $new_email, $u_id);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $error_msg = "ชื่อผู้ใช้ (Username) หรือ อีเมล นี้มีคนใช้งานแล้ว กรุณาใช้ชื่ออื่น";
        }
        mysqli_stmt_close($checkStmt);
    }

    // ถ้าไม่มี error เรื่องชื่อซ้ำ ให้ดำเนินการต่อ
    if (empty($error_msg)) {
        // กรณี: ต้องการเปลี่ยนรหัสผ่านด้วย
        if (!empty($new_password) || !empty($old_password)) {
            if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
                $error_msg = "กรุณากรอกข้อมูลรหัสผ่านให้ครบถ้วนหากต้องการเปลี่ยนรหัสผ่าน";
            } elseif ($new_password !== $confirm_password) {
                $error_msg = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
            } else {
                // ตรวจสอบรหัสผ่าน รองรับทั้งแบบธรรมดาและแบบเข้ารหัส
                $isPasswordCorrect = false;
                if (password_verify($old_password, $accountData['u_password'])) {
                    $isPasswordCorrect = true; 
                } elseif ($old_password === $accountData['u_password']) {
                    $isPasswordCorrect = true; 
                }

                if (!$isPasswordCorrect) {
                    $error_msg = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateSql = "UPDATE `account` SET u_username = ?, u_email = ?, u_password = ? WHERE u_id = ?";
                    if ($updateStmt = mysqli_prepare($conn, $updateSql)) {
                        mysqli_stmt_bind_param($updateStmt, "sssi", $new_username, $new_email, $hashed_password, $u_id);
                        if (mysqli_stmt_execute($updateStmt)) {
                            $success_msg = "อัปเดตข้อมูลบัญชีและรหัสผ่านเรียบร้อยแล้ว!";
                            $accountData['u_username'] = $new_username;
                            $accountData['u_email'] = $new_email;
                            $accountData['u_password'] = $hashed_password; 
                            
                            // อัปเดตรูป default กรณีเปลี่ยนชื่อ username ใหม่แล้วไม่มีรูปอัปโหลด
                            if (empty($accountData['u_image'])) {
                                $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($new_username) . "&background=F43F85&color=fff";
                            }
                        } else {
                            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                        }
                        mysqli_stmt_close($updateStmt);
                    }
                }
            }
        } 
        // กรณี: เปลี่ยนแค่ Username หรือ Email (ไม่เปลี่ยนรหัสผ่าน)
        else {
            $updateSql = "UPDATE `account` SET u_username = ?, u_email = ? WHERE u_id = ?";
            if ($updateStmt = mysqli_prepare($conn, $updateSql)) {
                mysqli_stmt_bind_param($updateStmt, "ssi", $new_username, $new_email, $u_id);
                if (mysqli_stmt_execute($updateStmt)) {
                    $success_msg = "อัปเดตข้อมูลบัญชีเรียบร้อยแล้ว!";
                    $accountData['u_username'] = $new_username;
                    $accountData['u_email'] = $new_email;
                    
                    // อัปเดตรูป default กรณีเปลี่ยนชื่อ username ใหม่แล้วไม่มีรูปอัปโหลด
                    if (empty($accountData['u_image'])) {
                        $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($new_username) . "&background=F43F85&color=fff";
                    }
                } else {
                    $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                }
                mysqli_stmt_close($updateStmt);
            }
        }
    }
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
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Lumina Beauty - รายละเอียดบัญชี</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <?= $totalCartItems ?>
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
            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft sticky top-28">
                <div class="flex flex-col space-y-2">
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="account.php">
                        <span class="material-icons-round">person</span>
                        <span>ข้อมูลส่วนตัว</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 bg-pink-50 dark:bg-pink-900/20 text-primary font-medium rounded-2xl transition-all shadow-sm" href="manageaccount.php">
                        <span class="material-icons-round">manage_accounts</span>
                        <span>รายละเอียดบัญชี</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="payment.php">
                        <span class="material-icons-round">credit_card</span>
                        <span>วิธีการชำระเงิน</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="history.php">
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
            
            <div class="bg-gradient-to-r from-purple-400 to-pink-400 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg">
                <div class="relative z-10 flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                        <span class="material-icons-round text-4xl">manage_accounts</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">รายละเอียดบัญชีการเข้าสู่ระบบ</h1>
                        <p class="text-pink-100 text-sm opacity-90">จัดการชื่อผู้ใช้งาน อีเมล และตั้งค่ารหัสผ่านใหม่เพื่อความปลอดภัย</p>
                    </div>
                </div>
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
            </div>

            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-8 shadow-soft border border-transparent dark:border-gray-700">
                
                <form action="manageaccount.php" method="POST" class="space-y-6">
                    
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2 mb-4">ข้อมูลบัญชีพื้นฐาน</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 ml-1">ชื่อผู้ใช้งาน (Username) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400 material-icons-round text-[20px]">alternate_email</span>
                                <input type="text" name="u_username" class="form-input pl-11" value="<?= htmlspecialchars($accountData['u_username'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 ml-1">อีเมล (Email) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400 material-icons-round text-[20px]">mail</span>
                                <input type="email" name="u_email" class="form-input pl-11" value="<?= htmlspecialchars($accountData['u_email'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 mt-6 border-t border-gray-100 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white pb-2 mb-4 flex items-center gap-2">
                            <span class="material-icons-round text-primary">vpn_key</span> 
                            เปลี่ยนรหัสผ่าน <span class="text-sm font-normal text-gray-400">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span>
                        </h3>

                        <div class="space-y-5 max-w-md">
                            <div>
                                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 ml-1">รหัสผ่านปัจจุบัน</label>
                                <div class="relative">
                                    <input type="password" name="old_password" id="old_password" class="form-input pr-10" placeholder="••••••••">
                                    <button type="button" onclick="togglePassword('old_password', 'eye_old')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition">
                                        <span class="material-icons-round text-[20px]" id="eye_old">visibility_off</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 ml-1">รหัสผ่านใหม่</label>
                                <div class="relative">
                                    <input type="password" name="new_password" id="new_password" class="form-input pr-10" placeholder="••••••••">
                                    <button type="button" onclick="togglePassword('new_password', 'eye_new')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition">
                                        <span class="material-icons-round text-[20px]" id="eye_new">visibility_off</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-600 dark:text-gray-400 mb-2 ml-1">ยืนยันรหัสผ่านใหม่</label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-input pr-10" placeholder="••••••••">
                                    <button type="button" onclick="togglePassword('confirm_password', 'eye_confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-primary transition">
                                        <span class="material-icons-round text-[20px]" id="eye_confirm">visibility_off</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-6 mt-6 border-t border-gray-100 dark:border-gray-700">
                        <button type="reset" class="px-6 py-2.5 rounded-full text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 font-medium transition">
                            คืนค่าเดิม
                        </button>
                        <button type="submit" class="px-8 py-2.5 rounded-full bg-primary hover:bg-pink-600 text-white font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition flex items-center gap-2">
                            <span class="material-icons-round text-[20px]">save</span> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>

                </form>
            </div>

        </section>
    </div>
</main>

<script>
    // ฟังก์ชัน แสดง/ซ่อน รหัสผ่าน
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility';
            icon.classList.add('text-primary');
        } else {
            input.type = 'password';
            icon.textContent = 'visibility_off';
            icon.classList.remove('text-primary');
        }
    }

    // แจ้งเตือนด้วย SweetAlert2 เมื่ออัปเดตสำเร็จ หรือ เกิดข้อผิดพลาด
    document.addEventListener("DOMContentLoaded", function() {
        <?php if (!empty($success_msg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= htmlspecialchars($success_msg) ?>',
                confirmButtonColor: '#F43F85', // สีชมพู Primary
                confirmButtonText: 'ตกลง',
                customClass: {
                    popup: 'rounded-3xl', // ขอบมนเข้ากับธีม
                    confirmButton: 'rounded-full px-6'
                }
            }).then(() => {
                // หลังจากกดตกลงให้เคลียร์ค่าช่องรหัสผ่าน (ป้องกัน Browser ถามให้เซฟรหัสซ้ำๆ)
                document.getElementById('old_password').value = '';
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';
            });
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '<?= htmlspecialchars($error_msg) ?>',
                confirmButtonColor: '#F43F85',
                confirmButtonText: 'ลองอีกครั้ง',
                customClass: {
                    popup: 'rounded-3xl',
                    confirmButton: 'rounded-full px-6'
                }
            });
        <?php endif; ?>
    });

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