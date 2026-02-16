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
// 1. จัดการการเพิ่ม/แก้ไขที่อยู่ (Insert/Update)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $label = trim($_POST['addr_label']);
    $name = trim($_POST['recipient_name']);
    $phone = trim($_POST['phone']);
    $detail = trim($_POST['address_line']);
    $district = trim($_POST['district']);
    $province = trim($_POST['province']);
    $zipcode = trim($_POST['zipcode']);

    if ($_POST['action'] === 'add_address') {
        // เช็คว่ามีที่อยู่เก่าไหม ถ้าไม่มีให้เซ็ตอันนี้เป็นที่อยู่หลักอัตโนมัติ
        $checkSql = "SELECT COUNT(*) as count FROM `user_address` WHERE u_id = ?";
        $stmtCheck = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($stmtCheck, "i", $u_id);
        mysqli_stmt_execute($stmtCheck);
        $is_first = (mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCheck))['count'] == 0) ? 1 : 0;
        mysqli_stmt_close($stmtCheck);

        $sql = "INSERT INTO `user_address` (u_id, addr_label, recipient_name, phone, address_line, district, province, zipcode, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isssssssi", $u_id, $label, $name, $phone, $detail, $district, $province, $zipcode, $is_first);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "เพิ่มที่อยู่ $label สำเร็จ!";
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
            mysqli_stmt_close($stmt);
        }
    } 
    elseif ($_POST['action'] === 'edit_address' && isset($_POST['addr_id'])) {
        $addr_id = (int)$_POST['addr_id'];
        $sql = "UPDATE `user_address` SET addr_label=?, recipient_name=?, phone=?, address_line=?, district=?, province=?, zipcode=? WHERE addr_id=? AND u_id=?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssii", $label, $name, $phone, $detail, $district, $province, $zipcode, $addr_id, $u_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "อัปเดตข้อมูลที่อยู่สำเร็จ!";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ==========================================
// 2. จัดการการลบ และ ตั้งค่าเริ่มต้น (GET)
// ==========================================
if (isset($_GET['delete_addr'])) {
    $delete_id = (int)$_GET['delete_addr'];
    $delSql = "DELETE FROM `user_address` WHERE addr_id = ? AND u_id = ? AND is_default = 0";
    if ($stmt = mysqli_prepare($conn, $delSql)) {
        mysqli_stmt_bind_param($stmt, "ii", $delete_id, $u_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: address.php");
        exit();
    }
}

if (isset($_GET['set_default'])) {
    $addr_id = (int)$_GET['set_default'];
    mysqli_query($conn, "UPDATE `user_address` SET is_default = 0 WHERE u_id = $u_id");
    mysqli_query($conn, "UPDATE `user_address` SET is_default = 1 WHERE addr_id = $addr_id AND u_id = $u_id");
    header("Location: address.php");
    exit();
}

// ==========================================
// 3. ดึงข้อมูลที่อยู่ทั้งหมด
// ==========================================
$addresses = [];
$sqlFetch = "SELECT * FROM `user_address` WHERE u_id = ? ORDER BY is_default DESC, addr_id DESC";
if ($stmt = mysqli_prepare($conn, $sqlFetch)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $addresses[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ==========================================
// 4. ดึงข้อมูล User (Profile Image) - โค้ดชุดเดียวกับ Payment
// ==========================================
$sql = "SELECT a.u_username, a.u_email, u.u_image FROM `account` a LEFT JOIN `user` u ON a.u_id = u.u_id WHERE a.u_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $u_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($userData = mysqli_fetch_assoc($result)) {
        if (!empty($userData['u_image']) && file_exists("../profile/uploads/" . $userData['u_image'])) {
            $profileImage = "../profile/uploads/" . $userData['u_image'];
        } else {
            $profileImage = "https://ui-avatars.com/api/?name=" . urlencode($userData['u_username']) . "&background=F43F85&color=fff";
        }
    }
    mysqli_stmt_close($stmt);
}

// นับจำนวนสินค้าในตะกร้า
$totalCartItems = 0; 
$sqlCartCount = "SELECT SUM(quantity) as total_qty FROM `cart` WHERE u_id = ?";
if ($stmtCartCount = mysqli_prepare($conn, $sqlCartCount)) {
    mysqli_stmt_bind_param($stmtCartCount, "i", $u_id);
    mysqli_stmt_execute($stmtCartCount);
    $resultCartCount = mysqli_stmt_get_result($stmtCartCount);
    if ($rowCartCount = mysqli_fetch_assoc($resultCartCount)) {
        $totalCartItems = ($rowCartCount['total_qty'] !== null) ? (int)$rowCartCount['total_qty'] : 0;
    }
    mysqli_stmt_close($stmtCartCount);
}
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>จัดการที่อยู่จัดส่ง - Lumina Beauty</title>
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
              DEFAULT: "1.5rem", 'xl': '1rem', '2xl': '1.5rem', '3xl': '2rem',
            },
            boxShadow: {
                'soft': '0 10px 40px -10px rgba(244, 63, 133, 0.15)',
            },
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
            width: 100%; border-radius: 1rem; border: 1px solid #FBCFE8; background-color: #FFF5F7;
            padding: 0.75rem 1.25rem; font-size: 0.95rem; color: #374151; transition: all 0.3s ease; outline: none;
        }
        .form-input:focus { border-color: #F43F85; box-shadow: 0 0 0 3px rgba(244, 63, 133, 0.2); background-color: #FFFFFF; }
        .dark .form-input { border-color: #4B5563; background-color: #1F2937; color: #E5E7EB; }
        ::-webkit-scrollbar { width: 8px; }
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
</nav>

<main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
    <div class="flex flex-col lg:flex-row gap-8">
        
        <aside class="w-full lg:w-1/4">
            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-6 shadow-soft sticky top-28 border border-transparent dark:border-gray-700">
                <div class="flex flex-col space-y-2">
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="account.php">
                        <span class="material-icons-round">person</span><span>ข้อมูลส่วนตัว</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="manageaccount.php">
                        <span class="material-icons-round">account_balance_wallet</span><span>รายละเอียดบัญชี</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="payment.php">
                        <span class="material-icons-round">credit_card</span><span>วิธีการชำระเงิน</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="#">
                        <span class="material-icons-round">history</span><span>ประวัติการสั่งซื้อ</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 bg-pink-50 dark:bg-pink-900/20 text-primary font-medium rounded-2xl transition-all shadow-sm" href="address.php">
                        <span class="material-icons-round">location_on</span><span>ที่อยู่จัดส่ง</span>
                    </a>
                    <a class="flex items-center space-x-3 px-4 py-3 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-all" href="../shop/favorites.php">
                        <span class="material-icons-round">favorite</span><span>สิ่งที่ถูกใจ</span>
                    </a>
                    <div class="border-t border-gray-100 dark:border-gray-700 my-2 pt-2"></div>
                    <a class="flex items-center space-x-3 px-4 py-3 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-2xl transition-all" href="../auth/logout.php">
                        <span class="material-icons-round">logout</span><span>ออกจากระบบ</span>
                    </a>
                </div>
            </div>
        </aside>

        <section class="w-full lg:w-3/4 space-y-6">
            <div class="bg-gradient-to-r from-pink-400 to-purple-400 rounded-3xl p-8 text-white relative overflow-hidden shadow-lg">
                <div class="relative z-10 flex items-center gap-4">
                    <div class="p-3 bg-white/20 rounded-2xl backdrop-blur-sm">
                        <span class="material-icons-round text-4xl">home</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold">ที่อยู่จัดส่ง</h1>
                        <p class="text-pink-100 text-sm opacity-90">จัดการสถานที่รับสินค้าของคุณเพื่อความสะดวกในการช้อป</p>
                    </div>
                </div>
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
            </div>

            <div class="bg-card-light dark:bg-card-dark rounded-3xl p-8 shadow-soft border border-transparent dark:border-gray-700">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white border-b border-gray-100 dark:border-gray-700 pb-2 mb-6 flex items-center gap-2">
                    <span class="material-icons-round text-primary">map</span> สถานที่จัดส่งของฉัน
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($addresses as $addr): ?>
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-3xl p-6 border-2 <?= $addr['is_default'] ? 'border-primary/40' : 'border-transparent' ?> relative group">
                            <?php if($addr['is_default']): ?>
                                <span class="absolute top-4 right-4 bg-primary text-white text-[10px] font-bold px-2 py-1 rounded-full shadow-sm">หลัก</span>
                            <?php endif; ?>
                            
                            <h4 class="font-bold text-primary mb-1 flex items-center gap-1">
                                <span class="material-icons-round text-sm">label</span> <?= htmlspecialchars($addr['addr_label']) ?>
                            </h4>
                            <p class="font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($addr['recipient_name']) ?></p>
                            <p class="text-xs text-gray-500 mb-3"><?= htmlspecialchars($addr['phone']) ?></p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-3 h-14 mb-4">
                                <?= htmlspecialchars($addr['address_line']) ?> 
                                <?= htmlspecialchars($addr['district']) ?> 
                                <?= htmlspecialchars($addr['province']) ?> 
                                <?= htmlspecialchars($addr['zipcode']) ?>
                            </p>
                            
                            <div class="flex gap-2">
                                <button onclick="openEditModal(<?= $addr['addr_id'] ?>, '<?= htmlspecialchars($addr['addr_label']) ?>', '<?= htmlspecialchars($addr['recipient_name']) ?>', '<?= htmlspecialchars($addr['phone']) ?>', '<?= htmlspecialchars($addr['address_line']) ?>', '<?= htmlspecialchars($addr['district']) ?>', '<?= htmlspecialchars($addr['province']) ?>', '<?= htmlspecialchars($addr['zipcode']) ?>')" class="flex-1 py-2 text-xs font-bold bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-100 transition-all">แก้ไข</button>
                                <?php if(!$addr['is_default']): ?>
                                    <a href="?set_default=<?= $addr['addr_id'] ?>" class="flex-1 py-2 text-xs font-bold bg-pink-50 text-primary rounded-xl text-center flex items-center justify-center">ใช้เป็นหลัก</a>
                                    <a href="#" onclick="confirmDelete(<?= $addr['addr_id'] ?>, event)" class="px-4 py-2 text-xs font-bold text-red-500 hover:bg-red-50 rounded-xl transition-all">ลบ</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button onclick="openModal('addAddressModal')" class="h-44 rounded-3xl border-2 border-dashed border-pink-300 dark:border-gray-600 flex flex-col items-center justify-center gap-2 hover:border-primary hover:bg-pink-50 dark:hover:bg-gray-800 transition-all group bg-white/50 dark:bg-card-dark">
                        <div class="w-10 h-10 rounded-full bg-pink-100 dark:bg-gray-700 flex items-center justify-center group-hover:bg-primary transition-all">
                            <span class="material-icons-round text-primary group-hover:text-white text-2xl">add</span>
                        </div>
                        <span class="font-bold text-gray-500 dark:text-gray-400 group-hover:text-primary">เพิ่มที่อยู่ใหม่</span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="addAddressModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-3xl p-8 w-full max-w-md shadow-2xl modal-content">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">เพิ่มที่อยู่จัดส่ง</h2>
        <form action="address.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_address">
            <input type="text" name="addr_label" placeholder="ป้ายกำกับ (เช่น บ้าน, ที่ทำงาน)" required class="form-input">
            <input type="text" name="recipient_name" placeholder="ชื่อ-นามสกุล ผู้รับ" required class="form-input">
            <input type="text" name="phone" placeholder="เบอร์โทรศัพท์" required class="form-input">
            <input type="text" name="address_line" placeholder="บ้านเลขที่ / ซอย / ถนน" required class="form-input">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="district" placeholder="อำเภอ / เขต" required class="form-input">
                <input type="text" name="province" placeholder="จังหวัด" required class="form-input">
            </div>
            <input type="text" name="zipcode" placeholder="รหัสไปรษณีย์" maxlength="5" required class="form-input">
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal('addAddressModal')" class="flex-1 py-3 bg-gray-100 text-gray-600 font-bold rounded-2xl">ยกเลิก</button>
                <button type="submit" class="flex-1 py-3 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/30">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<div id="editAddressModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[60] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark rounded-3xl p-8 w-full max-w-md shadow-2xl modal-content">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">แก้ไขที่อยู่</h2>
        <form action="address.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_address">
            <input type="hidden" name="addr_id" id="edit_addr_id">
            <input type="text" name="addr_label" id="edit_label" required class="form-input">
            <input type="text" name="recipient_name" id="edit_name" required class="form-input">
            <input type="text" name="phone" id="edit_phone" required class="form-input">
            <input type="text" name="address_line" id="edit_address_line" required class="form-input">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="district" id="edit_district" required class="form-input">
                <input type="text" name="province" id="edit_province" required class="form-input">
            </div>
            <input type="text" name="zipcode" id="edit_zipcode" maxlength="5" required class="form-input">
            <button type="submit" class="w-full py-3 bg-primary text-white font-bold rounded-2xl mt-4 transition-all">บันทึกการแก้ไข</button>
        </form>
    </div>
</div>

<script>
    if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    function toggleTheme() {
        const htmlEl = document.documentElement;
        htmlEl.classList.toggle('dark');
        localStorage.setItem('theme', htmlEl.classList.contains('dark') ? 'dark' : 'light');
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.remove('opacity-0'), 10);
    }
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    function openEditModal(id, label, name, phone, detail, district, province, zipcode) {
        document.getElementById('edit_addr_id').value = id;
        document.getElementById('edit_label').value = label;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_address_line').value = detail;
        document.getElementById('edit_district').value = district;
        document.getElementById('edit_province').value = province;
        document.getElementById('edit_zipcode').value = zipcode;
        openModal('editAddressModal');
    }

    function confirmDelete(id, event) {
        event.preventDefault();
        Swal.fire({
            title: 'ยืนยันการลบ?', text: "หากลบแล้วจะไม่สามารถกู้คืนได้!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#F43F85', cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ลบเลย!', cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '?delete_addr=' + id;
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($success_msg): ?>
            Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= $success_msg ?>', confirmButtonColor: '#F43F85', customClass: { popup: 'rounded-3xl' }});
        <?php endif; ?>
    });
</script>
</body></html>