<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php';

// ตรวจสอบสถานะการล็อกอิน
$isLoggedIn = false;
$profileImage = "https://ui-avatars.com/api/?name=Guest&background=E5E7EB&color=9CA3AF"; 
$cartItems = [];
$totalPrice = 0;
$totalItems = 0;
$shippingFee = 0; // ค่าจัดส่งเริ่มต้น (อาจจะคำนวณจากยอดสั่งซื้อ)

if (isset($_SESSION['u_id'])) {
    $isLoggedIn = true;
    $u_id = $_SESSION['u_id'];
    
    // ----------------------------------------------------
    // 📌 1. ระบบจัดการตะกร้า (เพิ่ม/ลด/ลบ) รับค่าจากฟอร์ม
    // ----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $p_id = isset($_POST['p_id']) ? (int)$_POST['p_id'] : 0;
        
        if ($p_id > 0) {
            if ($_POST['action'] === 'add') {
                // 1.1 เช็คว่ามีสินค้านี้ในตะกร้าหรือยัง
                $checkSql = "SELECT cart_id, quantity FROM `cart` WHERE u_id = ? AND p_id = ?";
                $stmtCheck = mysqli_prepare($conn, $checkSql);
                mysqli_stmt_bind_param($stmtCheck, "ii", $u_id, $p_id);
                mysqli_stmt_execute($stmtCheck);
                $resultCheck = mysqli_stmt_get_result($stmtCheck);
                
                if ($row = mysqli_fetch_assoc($resultCheck)) {
                    // ถ้ามีแล้ว -> ให้บวกจำนวนเพิ่ม +1
                    $newQty = $row['quantity'] + 1;
                    $updateSql = "UPDATE `cart` SET quantity = ? WHERE cart_id = ?";
                    $stmtUp = mysqli_prepare($conn, $updateSql);
                    mysqli_stmt_bind_param($stmtUp, "ii", $newQty, $row['cart_id']);
                    mysqli_stmt_execute($stmtUp);
                } else {
                    // ถ้ายังไม่มี -> เพิ่มลงตะกร้าใหม่ (จำนวน 1)
                    $insertSql = "INSERT INTO `cart` (u_id, p_id, quantity) VALUES (?, ?, 1)";
                    $stmtIn = mysqli_prepare($conn, $insertSql);
                    mysqli_stmt_bind_param($stmtIn, "ii", $u_id, $p_id);
                    mysqli_stmt_execute($stmtIn);
                }
            } 
            elseif ($_POST['action'] === 'update_qty' && isset($_POST['qty'])) {
                // 1.2 อัปเดตจำนวนสินค้า (จากปุ่ม + / - ในหน้าตะกร้า)
                $qty = (int)$_POST['qty'];
                if ($qty > 0) {
                    $upSql = "UPDATE `cart` SET quantity = ? WHERE u_id = ? AND p_id = ?";
                    $stmtUp = mysqli_prepare($conn, $upSql);
                    mysqli_stmt_bind_param($stmtUp, "iii", $qty, $u_id, $p_id);
                    mysqli_stmt_execute($stmtUp);
                } else {
                    // ถ้าปรับจนเหลือ 0 ให้ลบออก
                    $_POST['action'] = 'remove';
                }
            }
            
            if ($_POST['action'] === 'remove') {
                // 1.3 ลบสินค้าออกจากตะกร้า
                $delSql = "DELETE FROM `cart` WHERE u_id = ? AND p_id = ?";
                $stmtDel = mysqli_prepare($conn, $delSql);
                mysqli_stmt_bind_param($stmtDel, "ii", $u_id, $p_id);
                mysqli_stmt_execute($stmtDel);
            }
        }
        // ป้องกันการกด Refresh แล้วฟอร์มทำงานซ้ำ
        header("Location: cart.php");
        exit();
    }

    // ----------------------------------------------------
    // 📌 2. ดึงข้อมูลสินค้าในตะกร้าของผู้ใช้งาน
    // ----------------------------------------------------
    $sqlCart = "SELECT c.cart_id, c.quantity, p.p_id, p.p_name, p.p_price, p.p_image 
                FROM `cart` c 
                JOIN `product` p ON c.p_id = p.p_id 
                WHERE c.u_id = ? ORDER BY c.created_at DESC";
                
    if ($stmtCart = mysqli_prepare($conn, $sqlCart)) {
        mysqli_stmt_bind_param($stmtCart, "i", $u_id);
        mysqli_stmt_execute($stmtCart);
        $resultCart = mysqli_stmt_get_result($stmtCart);
        
        while ($row = mysqli_fetch_assoc($resultCart)) {
            $cartItems[] = $row;
            // คำนวณยอดรวม
            $totalPrice += ($row['p_price'] * $row['quantity']);
            $totalItems += $row['quantity'];
        }
        mysqli_stmt_close($stmtCart);
    }
    
    // ตั้งค่าจัดส่ง (สมมติ: ซื้อครบ 1,000 ส่งฟรี ถ้าไม่ถึงคิดค่าส่ง 50)
    if ($totalPrice > 0 && $totalPrice < 1000) {
        $shippingFee = 50;
    }

    // ----------------------------------------------------
    // 📌 3. ดึงข้อมูลโปรไฟล์ผู้ใช้
    // ----------------------------------------------------
    $sqlUser = "SELECT a.u_username, u.u_image 
            FROM `account` a 
            LEFT JOIN `user` u ON a.u_id = u.u_id 
            WHERE a.u_id = ?";
    if ($stmtUser = mysqli_prepare($conn, $sqlUser)) {
        mysqli_stmt_bind_param($stmtUser, "i", $u_id);
        mysqli_stmt_execute($stmtUser);
        $resultUser = mysqli_stmt_get_result($stmtUser);
        if ($accountData = mysqli_fetch_assoc($resultUser)) {
            $displayName = $accountData['u_username'] ?? 'User';
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($displayName) . "&background=F43F85&color=fff";
            
            if (!empty($accountData['u_image']) && file_exists("../profile/uploads/" . $accountData['u_image'])) {
                $profileImage = "../profile/uploads/" . $accountData['u_image'];
            }
        }
        mysqli_stmt_close($stmtUser);
    }
}
$netTotal = $totalPrice + $shippingFee; // ยอดสุทธิ
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>หน้าตะกร้าสินค้า - Lumina Beauty</title>
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
                        'glow': '0 0 20px rgba(244, 63, 133, 0.3)',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out 3s infinite',
                        'float-slow': 'float 8s ease-in-out 1s infinite',
                        'float-fast': 'float 4s ease-in-out infinite',
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
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #FBCFE8; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #F43F85; }
        
        /* สไตล์เอาลูกศรปรับตัวเลขขึ้นลงออก */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
          -webkit-appearance: none; 
          margin: 0; 
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen relative overflow-x-hidden">

<div class="fixed top-0 left-0 w-full h-full overflow-hidden pointer-events-none z-0">
    <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-pink-200 dark:bg-pink-900 blur-3xl opacity-30 animate-pulse float-slow"></div>
    <div class="absolute top-[40%] -right-[10%] w-[40%] h-[40%] rounded-full bg-purple-200 dark:bg-purple-900 blur-3xl opacity-30 float-medium"></div>
</div>

<header class="sticky top-0 z-50 glass-panel shadow-sm">
    <div class="w-full px-6 md:px-10 lg:px-16"> 
        <div class="flex justify-between items-center h-20 w-full">
            <a href="../home.php" class="flex-shrink-0 flex items-center cursor-pointer group">
                <span class="material-icons-round text-primary text-4xl">spa</span>
                <span class="ml-2 text-2xl font-bold font-display text-primary tracking-wide">Lumina</span>
            </a>
            
                <div class="flex items-center space-x-5">
                <a href="favorites.php" class="hover:text-primary transition relative flex items-center text-gray-500 dark:text-gray-300">
                    <span class="material-icons-round text-2xl">favorite_border</span>
                </a>
                <a href="cart.php" class="text-primary hover:text-pink-600 transition relative flex items-center justify-center">
                    <span class="material-icons-round text-2xl">shopping_bag</span>
                    <span class="absolute -top-1.5 -right-2 bg-primary text-white text-[10px] font-bold rounded-full h-[18px] w-[18px] flex items-center justify-center border-2 border-white dark:border-gray-800"><?= $totalItems ?></span>
                </a>
            <button class="hover:text-primary transition flex items-center justify-center" onclick="toggleTheme()">
                <span class="material-icons-round dark:hidden text-2xl text-gray-500">dark_mode</span>
                <span class="material-icons-round hidden dark:block text-yellow-400 text-2xl">light_mode</span>
            </button>
                
                <a href="../profile/account.php" class="relative w-9 h-9 rounded-full bg-gradient-to-tr from-pink-300 to-purple-300 p-[2px] shadow-sm hover:shadow-md hover:scale-105 transition-all cursor-pointer flex items-center justify-center">
                    <img alt="Profile" class="w-full h-full rounded-full object-cover bg-white dark:bg-gray-800" src="<?= htmlspecialchars($profileImage) ?>" onerror="this.src='https://ui-avatars.com/api/?name=User&background=F43F85&color=fff'"/>
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</header>

<main class="relative z-10 w-full min-h-[calc(100vh-80px)] pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-16">
            
            <div class="lg:col-span-8 space-y-6">
                <h2 class="text-2xl font-display font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                    รายการสินค้า 
                    <span class="ml-2 text-sm font-body font-normal text-gray-500 dark:text-gray-400 bg-white dark:bg-card-dark px-3 py-1 rounded-full shadow-sm border border-gray-100 dark:border-gray-700"><?= $totalItems ?> ชิ้น</span>
                </h2>

                <?php if (!$isLoggedIn): ?>
                    <div class="bg-card-light dark:bg-card-dark rounded-3xl p-10 shadow-soft flex flex-col items-center justify-center text-center border border-transparent dark:border-gray-700 min-h-[300px]">
                        <div class="w-24 h-24 bg-purple-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 text-accent opacity-80">
                            <span class="material-icons-round text-5xl">lock</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">กรุณาเข้าสู่ระบบ</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">เข้าสู่ระบบหรือสมัครสมาชิก เพื่อดูและจัดการตะกร้าสินค้าของคุณ</p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 w-full justify-center max-w-sm">
                            <a href="../auth/login.php" class="w-full sm:w-1/2 bg-primary hover:bg-pink-600 text-white px-6 py-3 rounded-full font-medium shadow-md transition-all text-center">
                                เข้าสู่ระบบ
                            </a>
                            <a href="../auth/register.php" class="w-full sm:w-1/2 bg-pink-50 dark:bg-gray-800 text-primary dark:text-pink-400 hover:bg-pink-100 dark:hover:bg-gray-700 px-6 py-3 rounded-full font-medium transition-all text-center">
                                สมัครสมาชิก
                            </a>
                        </div>
                    </div>
                
                <?php elseif (count($cartItems) == 0): ?>
                    <div class="bg-card-light dark:bg-card-dark rounded-3xl p-10 shadow-soft flex flex-col items-center justify-center text-center border border-transparent dark:border-gray-700 min-h-[300px]">
                        <div class="w-24 h-24 bg-pink-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 text-primary opacity-80">
                            <span class="material-icons-round text-5xl">remove_shopping_cart</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">ยังไม่มีสินค้าในตะกร้า</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">เลือกหาสินค้าความงามที่โดนใจ แล้วเพิ่มลงตะกร้าได้เลย!</p>
                        <a href="products.php" class="bg-gradient-to-r from-pink-400 to-primary hover:from-primary hover:to-pink-600 text-white px-8 py-3 rounded-full font-medium shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                            ไปช้อปปิ้งกันเลย
                        </a>
                    </div>
                
                <?php else: ?>
                    <div class="bg-card-light dark:bg-card-dark rounded-3xl shadow-soft p-2 sm:p-6 border border-transparent dark:border-gray-700 space-y-4">
                        
                        <?php foreach($cartItems as $item): 
                            $img = (!empty($item['p_image']) && file_exists("../uploads/products/" . $item['p_image'])) 
                                    ? "../uploads/products/" . $item['p_image'] 
                                    : "https://via.placeholder.com/150";
                            $subPrice = $item['p_price'] * $item['quantity'];
                        ?>
                        <div class="flex items-center bg-gray-50 dark:bg-gray-800/50 p-4 rounded-2xl relative border border-transparent hover:border-pink-100 dark:hover:border-gray-700 transition-colors">
                            
                            <div class="w-24 h-24 sm:w-28 sm:h-28 flex-shrink-0 bg-white dark:bg-gray-800 rounded-xl overflow-hidden p-1 shadow-sm">
                                <img src="<?= $img ?>" alt="<?= htmlspecialchars($item['p_name']) ?>" class="w-full h-full object-cover rounded-lg">
                            </div>
                            
                            <div class="ml-4 flex-1 flex flex-col justify-between h-full py-1">
                                <div>
                                    <h3 class="text-md sm:text-lg font-bold text-gray-800 dark:text-white line-clamp-1 pr-8"><?= htmlspecialchars($item['p_name']) ?></h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ราคา: ฿<?= number_format($item['p_price']) ?> / ชิ้น</p>
                                </div>
                                
                                <div class="flex items-center justify-between mt-4">
                                    <div class="flex items-center bg-white dark:bg-gray-700 rounded-full border border-gray-200 dark:border-gray-600 shadow-sm p-1">
                                        <form action="cart.php" method="POST" class="flex items-center m-0">
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="p_id" value="<?= $item['p_id'] ?>">
                                            <input type="hidden" name="qty" value="<?= $item['quantity'] - 1 ?>">
                                            <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-600 rounded-full transition-colors" <?= $item['quantity'] <= 1 ? 'disabled class="opacity-50"' : '' ?>>
                                                <span class="material-icons-round text-sm">remove</span>
                                            </button>
                                        </form>
                                        
                                        <span class="w-8 text-center font-bold text-gray-800 dark:text-white text-sm"><?= $item['quantity'] ?></span>
                                        
                                        <form action="cart.php" method="POST" class="flex items-center m-0">
                                            <input type="hidden" name="action" value="update_qty">
                                            <input type="hidden" name="p_id" value="<?= $item['p_id'] ?>">
                                            <input type="hidden" name="qty" value="<?= $item['quantity'] + 1 ?>">
                                            <button type="submit" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-primary hover:bg-pink-50 dark:hover:bg-gray-600 rounded-full transition-colors">
                                                <span class="material-icons-round text-sm">add</span>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="text-right">
                                        <span class="text-lg font-bold text-primary">฿<?= number_format($subPrice) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <form action="cart.php" method="POST" class="absolute top-4 right-4 m-0">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="p_id" value="<?= $item['p_id'] ?>">
                            <button type="button" class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium font-body text-gray-400 hover:text-red-500 bg-white dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-900/20 border border-gray-100 dark:border-gray-700 hover:border-red-200 dark:hover:border-red-800 rounded-full shadow-sm transition-all group" onclick="confirmCartDelete(event, this.closest('form'))">
                                <span class="material-icons-round text-[16px] group-hover:scale-110 transition-transform">delete_outline</span>
                                <span class="mt-[1px]">ลบ</span>
                            </button>
                            </form>
                            
                        </div>
                        <?php endforeach; ?>
                        
                    </div>
                <?php endif; ?>

            </div>

            <div class="lg:col-span-4 relative">
                <div class="sticky top-28">
                    <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft relative z-10 border border-transparent dark:border-gray-700">
                        <h2 class="text-xl font-display font-bold text-gray-800 dark:text-white mb-6 border-b border-dashed border-gray-100 dark:border-gray-700 pb-4">สรุปคำสั่งซื้อ</h2>
                        
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                <span>ราคารวม (Subtotal)</span>
                                <span class="font-medium">฿<?= number_format($totalPrice) ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                <span>ส่วนลด (Discount)</span>
                                <span class="text-primary font-medium">-฿0</span>
                            </div>
                            <div class="flex justify-between text-gray-600 dark:text-gray-300">
                                <span>ค่าจัดส่ง (Shipping)</span>
                                <?php if($totalPrice == 0): ?>
                                    <span class="text-gray-500 font-medium">-</span>
                                <?php elseif($shippingFee == 0): ?>
                                    <span class="text-green-500 font-bold">ฟรี!</span>
                                <?php else: ?>
                                    <span class="font-medium">฿<?= $shippingFee ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-300 text-xs p-3 rounded-xl flex items-center gap-2 border border-blue-100 dark:border-blue-800">
                                <span class="material-icons-round text-[18px]">local_shipping</span>
                                <?php if ($totalPrice >= 1000): ?>
                                    ยินดีด้วย! คุณได้รับสิทธิ์จัดส่งฟรี
                                <?php else: ?>
                                    ซื้อเพิ่มอีก ฿<?= number_format(1000 - $totalPrice) ?> เพื่อรับสิทธิ์จัดส่งฟรี!
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="border-t border-dashed border-gray-100 dark:border-gray-700 pt-4 mb-8">
                            <div class="flex justify-between items-end">
                                <span class="text-lg font-bold text-gray-800 dark:text-white">ยอดสุทธิ</span>
                                <span class="text-2xl font-bold text-primary font-display">฿<?= number_format($netTotal) ?></span>
                            </div>
                            <div class="text-right text-[11px] text-gray-400 mt-1">รวมภาษีมูลค่าเพิ่มแล้ว</div>
                        </div>

                        <?php if ($totalItems > 0): ?>
                            <a href="checkout.php" class="w-full bg-primary hover:bg-pink-600 text-white py-4 rounded-xl font-bold text-lg flex items-center justify-center group shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-300">
                                ไปหน้าชำระเงิน
                                <span class="material-icons-round ml-2 group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        <?php else: ?>
                            <button class="w-full bg-gray-300 dark:bg-gray-700 text-white py-4 rounded-xl font-bold text-lg cursor-not-allowed flex items-center justify-center transition-all duration-300" disabled>
                                ไปหน้าชำระเงิน
                                <span class="material-icons-round ml-2">arrow_forward</span>
                            </button>
                        <?php endif; ?>
                        
                        <a href="products.php" class="block w-full mt-4 text-gray-500 dark:text-gray-400 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors text-center">
                            ซื้อสินค้าต่อ
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<footer class="bg-card-light dark:bg-card-dark py-10 border-t border-pink-50 dark:border-gray-800">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <div class="flex justify-center items-center mb-6 opacity-80">
            <span class="text-primary material-icons-round text-2xl mr-2">local_florist</span>
            <span class="font-display font-bold text-xl text-gray-800 dark:text-white">Lumina Beauty</span>
        </div>
        <p class="text-gray-400 text-sm">© 2026 Lumina Beauty. All rights reserved.</p>
    </div>
</footer>

<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark');
    }
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    // ฟังก์ชันแจ้งเตือนยืนยันการลบสินค้าออกจากตะกร้าด้วย SweetAlert2
    function confirmCartDelete(event, formElement) {
        event.preventDefault(); // หยุดการทำงานของปุ่มไว้ก่อน
        
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "ต้องการลบสินค้านี้ออกจากตะกร้าใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#F43F85', // สีชมพู Primary
            cancelButtonColor: '#9CA3AF',  // สีเทา
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก',
            customClass: { 
                popup: 'rounded-3xl',
                confirmButton: 'rounded-full px-6 font-medium',
                cancelButton: 'rounded-full px-6 font-medium'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // ถ้ากด "ใช่, ลบเลย!" สั่งให้ฟอร์มทำงาน (ลบข้อมูล)
                formElement.submit();
            }
        });
    }
</script>

</body></html>