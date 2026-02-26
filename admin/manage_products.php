<?php
session_start();
require_once '../config/connectdbuser.php';

// ดึงหมวดหมู่
$categories = [];
$resCat = mysqli_query($conn, "SELECT * FROM category");
while($c = mysqli_fetch_assoc($resCat)) { $categories[] = $c; }

// ==========================================
// 1. จัดการเพิ่มสินค้า (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $name = $_POST['p_name'];
    $sku = $_POST['p_sku'];
    $cat_id = $_POST['c_id'];
    $price = $_POST['p_price'];
    $stock = $_POST['p_stock'];
    $detail = $_POST['p_detail'];

    $sql = "INSERT INTO product (p_name, p_sku, c_id, p_price, p_stock, p_detail) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssidis", $name, $sku, $cat_id, $price, $stock, $detail);
    
    if (mysqli_stmt_execute($stmt)) {
        $product_id = mysqli_insert_id($conn);

        // อัปโหลดรูปหน้าปก
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $main_img_name = "prod_" . $product_id . "_main." . $ext;
            move_uploaded_file($_FILES['main_image']['tmp_name'], "../uploads/products/" . $main_img_name);
            mysqli_query($conn, "UPDATE product SET p_image = '$main_img_name' WHERE p_id = $product_id");
        }

        // อัปโหลดรูป Gallery
        if (isset($_FILES['gallery_images'])) {
            foreach ($_FILES['gallery_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['gallery_images']['error'][$key] == 0) {
                    $ext = pathinfo($_FILES['gallery_images']['name'][$key], PATHINFO_EXTENSION);
                    $gall_img_name = "prod_" . $product_id . "_gall_" . time() . "_" . $key . "." . $ext;
                    if (move_uploaded_file($tmp_name, "../uploads/products/" . $gall_img_name)) {
                        mysqli_query($conn, "INSERT INTO product_images (p_id, image_url) VALUES ($product_id, '$gall_img_name')");
                    }
                }
            }
        }

        // เพิ่มสี
        if (isset($_POST['color_names']) && isset($_POST['color_hexes'])) {
            $c_names = $_POST['color_names'];
            $c_hexes = $_POST['color_hexes'];
            for ($i = 0; $i < count($c_names); $i++) {
                if (!empty($c_names[$i])) {
                    $c_name = mysqli_real_escape_string($conn, $c_names[$i]);
                    $c_hex = mysqli_real_escape_string($conn, $c_hexes[$i]);
                    mysqli_query($conn, "INSERT INTO product_colors (p_id, color_name, color_hex) VALUES ($product_id, '$c_name', '$c_hex')");
                }
            }
        }
        
        $_SESSION['success_msg'] = "เพิ่มสินค้าสำเร็จ!";
    } else {
        $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการบันทึก";
    }
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 2. จัดการแก้ไขสินค้าเบื้องต้น (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_product') {
    $p_id = (int)$_POST['edit_p_id'];
    $name = $_POST['p_name'];
    $sku = $_POST['p_sku'];
    $cat_id = $_POST['c_id'];
    $price = $_POST['p_price'];
    $stock = $_POST['p_stock'];
    $detail = $_POST['p_detail'];

    $sql = "UPDATE product SET p_name=?, p_sku=?, c_id=?, p_price=?, p_stock=?, p_detail=? WHERE p_id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssidis", $name, $sku, $cat_id, $price, $stock, $detail, $p_id);
    mysqli_stmt_execute($stmt);

    // ถ้ามีการอัปโหลดรูปปกใหม่ ให้เปลี่ยนรูป
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
        $ext = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
        $main_img_name = "prod_" . $p_id . "_main_" . time() . "." . $ext;
        move_uploaded_file($_FILES['main_image']['tmp_name'], "../uploads/products/" . $main_img_name);
        mysqli_query($conn, "UPDATE product SET p_image = '$main_img_name' WHERE p_id = $p_id");
    }

    $_SESSION['success_msg'] = "แก้ไขข้อมูลสินค้าสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 3. จัดการเปิด/ปิดสถานะ (GET)
// ==========================================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $t_id = (int)$_GET['id'];
    $current = (int)$_GET['toggle_status'];
    $new_status = $current === 1 ? 0 : 1; 
    mysqli_query($conn, "UPDATE product SET status = $new_status WHERE p_id = $t_id");
    
    $_SESSION['success_msg'] = "อัปเดตสถานะสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// ==========================================
// 4. จัดการลบสินค้า (GET)
// ==========================================
if(isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM product WHERE p_id = $del_id");
    mysqli_query($conn, "DELETE FROM product_images WHERE p_id = $del_id");
    mysqli_query($conn, "DELETE FROM product_colors WHERE p_id = $del_id");
    $_SESSION['success_msg'] = "ลบสินค้าสำเร็จ!";
    header("Location: manage_products.php");
    exit();
}

// สถิติ
$stat_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product"))['c'];
$stat_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE p_stock = 0"))['c'];
$stat_low = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM product WHERE p_stock > 0 AND p_stock <= 10"))['c'];

// โชว์แจ้งเตือนออเดอร์ (Sidebar)
$today = date('Y-m-d');
$newOrders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(order_id) as c FROM orders WHERE DATE(created_at) = '$today'"))['c'] ?? 0;

// ดึงสินค้า
$products = [];
$resProd = mysqli_query($conn, "SELECT p.*, c.c_name FROM product p LEFT JOIN category c ON p.c_id = c.c_id ORDER BY p.p_id DESC");
while($p = mysqli_fetch_assoc($resProd)) { $products[] = $p; }
?>
<!DOCTYPE html>
<html lang="th"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>จัดการสินค้า - Lumina Beauty Admin</title>

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
    /* Scrollbar Modal สี */
    .color-scroll::-webkit-scrollbar { width: 4px; }
    .color-scroll::-webkit-scrollbar-thumb { background: #fce7f3; border-radius: 4px; }
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
                <a class="nav-item-active flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-300 group" href="#">
                    <span class="material-icons-round">inventory_2</span>
                    <span class="font-bold text-[15px]">จัดการสินค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="manage_orders.php">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">receipt_long</span>
                    <span class="font-medium text-[15px]">รายการสั่งซื้อ</span>
                    <?php if($newOrders > 0): ?>
                        <span class="ml-auto bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= $newOrders ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="#">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">group</span>
                    <span class="font-medium text-[15px]">ข้อมูลลูกค้า</span>
                </a>
                <a class="nav-item flex items-center gap-4 px-5 py-3.5 rounded-2xl text-text-muted transition-all duration-300 group hover:pl-6" href="#">
                    <span class="material-icons-round group-hover:scale-110 transition-transform">settings</span>
                    <span class="font-medium text-[15px]">ตั้งค่าระบบ</span>
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
        <header class="flex items-center justify-between px-6 py-4 lg:px-10 lg:py-5 glass-panel sticky top-0 z-50">
            <div class="flex items-center gap-4 lg:hidden">
                <button class="p-2 text-text-main hover:bg-pink-50 rounded-xl transition-colors">
                    <span class="material-icons-round">menu</span>
                </button>
                <span class="font-bold text-xl text-primary flex items-center gap-1"><span class="material-icons-round">spa</span> Lumina</span>
            </div>
            
            <div class="hidden md:flex flex-1 max-w-md relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors text-[20px]">search</span>
                </div>
                <input class="block w-full pl-12 pr-4 py-2.5 rounded-full border border-pink-100 bg-white shadow-sm text-sm placeholder-gray-400 focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="ค้นหาสินค้า (ชื่อ, รหัส, หมวดหมู่)..." type="text"/>
            </div>
            
            <div class="flex items-center gap-4 lg:gap-6 ml-auto">
                <button class="relative p-2.5 rounded-full bg-white text-gray-500 hover:text-primary hover:bg-pink-50 transition-colors shadow-sm border border-pink-50">
                    <span class="material-icons-round text-[22px]">notifications</span>
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
                
                <div class="relative group flex items-center">
                    <?php 
                        $adminName = $_SESSION['admin_username'] ?? 'Admin'; 
                        $adminAvatar = "https://ui-avatars.com/api/?name=" . urlencode($adminName) . "&background=a855f7&color=fff";
                    ?>
                    <a href="#" class="block w-11 h-11 rounded-full bg-gradient-to-tr from-purple-400 to-indigo-400 p-[2px] shadow-sm hover:shadow-glow hover:scale-105 transition-all cursor-pointer">
                        <div class="bg-white rounded-full p-[2px] w-full h-full">
                            <img alt="Admin Profile" class="w-full h-full rounded-full object-cover" src="<?= $adminAvatar ?>"/>
                        </div>
                    </a>
                    
                    <div class="absolute right-0 hidden pt-4 top-full w-[300px] z-50 group-hover:block cursor-default">
                        <div class="bg-white rounded-3xl shadow-soft border border-pink-100 overflow-hidden p-5 relative">
                            
                            <div class="text-center mb-4">
                                <span class="text-sm font-bold text-purple-500 bg-purple-50 px-3 py-1 rounded-full">
                                    Administrator Mode
                                </span>
                            </div>

                            <div class="flex justify-center relative mb-3">
                                <div class="rounded-full p-[3px] bg-purple-500 shadow-md">
                                    <div class="bg-white rounded-full p-[2px] w-16 h-16">
                                        <img src="<?= $adminAvatar ?>" alt="Profile" class="w-full h-full rounded-full object-cover">
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mb-6">
                                <h3 class="text-[20px] font-bold text-gray-800">สวัสดี, คุณ <?= htmlspecialchars($adminName) ?></h3>
                                <p class="text-xs text-gray-500 mt-1">ผู้จัดการระบบสูงสุด</p>
                            </div>

                            <div class="flex flex-col gap-3">
                                <a href="#" class="w-full flex items-center justify-center gap-2 bg-white border-2 border-purple-500 hover:bg-purple-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-purple-500">
                                    <span class="material-icons-round text-[18px]">manage_accounts</span> จัดการบัญชี
                                </a>
                                <a href="../auth/logout.php" class="w-full flex items-center justify-center gap-2 bg-white border-2 border-red-500 hover:bg-red-500 hover:text-white rounded-full py-2.5 transition text-[14px] font-semibold text-red-500">
                                    <span class="material-icons-round text-[18px]">logout</span> ออกจากระบบ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-6 lg:p-8 flex flex-col gap-8 max-w-[1600px] mx-auto w-full">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-800 tracking-tight">จัดการสินค้าคงคลัง</h1>
                    <p class="text-gray-500 font-medium mt-1 text-sm">เพิ่ม, แก้ไข, จัดการสต๊อก และกำหนดสถานะการขายสินค้า</p>
                </div>
                <button onclick="openModal('addProductModal')" class="bg-primary hover:bg-pink-600 text-white px-6 py-3.5 rounded-full font-bold text-sm shadow-lg shadow-primary/30 flex items-center gap-2 transition-transform transform hover:-translate-y-1">
                    <span class="material-icons-round">add</span> เพิ่มสินค้าใหม่
                </button>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-pink-50 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-pink-100 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">inventory_2</span>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm font-semibold">สินค้าทั้งหมด</p>
                            <h3 class="text-3xl font-extrabold text-gray-800"><?= $stat_total ?></h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-red-50 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-red-100 flex items-center justify-center text-red-500 group-hover:bg-red-500 group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">production_quantity_limits</span>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm font-semibold">สินค้าที่หมด</p>
                            <h3 class="text-3xl font-extrabold text-gray-800"><?= $stat_out ?></h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-card hover:shadow-soft transition-all duration-300 border border-gray-100 group relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-yellow-50 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-yellow-100 flex items-center justify-center text-yellow-600 group-hover:bg-yellow-500 group-hover:text-white transition-colors">
                            <span class="material-icons-round text-3xl">low_priority</span>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm font-semibold">สินค้าใกล้หมด</p>
                            <h3 class="text-3xl font-extrabold text-gray-800"><?= $stat_low ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-card overflow-hidden border border-gray-100 mb-10">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 text-gray-500 text-[13px] uppercase tracking-wider border-b border-gray-100">
                                <th class="px-6 py-5 font-bold pl-8">รูปภาพ</th>
                                <th class="px-6 py-5 font-bold">ชื่อสินค้า / SKU</th>
                                <th class="px-6 py-5 font-bold">หมวดหมู่</th>
                                <th class="px-6 py-5 font-bold">ราคา</th>
                                <th class="px-6 py-5 font-bold text-center">สต็อก</th>
                                <th class="px-6 py-5 font-bold text-center">สถานะ</th>
                                <th class="px-6 py-5 font-bold text-right pr-8">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php foreach($products as $p): 
                                $img = (!empty($p['p_image']) && file_exists("../uploads/products/".$p['p_image'])) ? "../uploads/products/".$p['p_image'] : "https://via.placeholder.com/150";
                            ?>
                            <tr class="hover:bg-pink-50/30 transition-colors group">
                                <td class="px-6 py-4 pl-8">
                                    <div class="w-16 h-16 rounded-2xl overflow-hidden border border-gray-100 shadow-sm bg-white">
                                        <img src="<?= $img ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-800 text-base mb-0.5 group-hover:text-primary transition-colors"><?= htmlspecialchars($p['p_name']) ?></span>
                                        <span class="text-[12px] text-gray-400 font-mono bg-gray-50 w-fit px-2 py-0.5 rounded-md border border-gray-100">SKU: <?= htmlspecialchars($p['p_sku']) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold bg-pink-50 text-primary border border-pink-100 shadow-sm">
                                        <?= htmlspecialchars($p['c_name'] ?? 'ไม่มีหมวดหมู่') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-800 text-base">฿<?= number_format($p['p_price']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if($p['p_stock'] == 0): ?>
                                        <span class="font-bold text-red-500 bg-red-50 px-3 py-1 rounded-lg text-xs shadow-sm">สินค้าหมด</span>
                                    <?php elseif($p['p_stock'] <= 10): ?>
                                        <span class="font-bold text-yellow-600 bg-yellow-50 px-3 py-1 rounded-lg text-xs shadow-sm">เหลือ <?= $p['p_stock'] ?></span>
                                    <?php else: ?>
                                        <span class="font-bold text-green-600 bg-green-50 px-3 py-1 rounded-lg text-xs shadow-sm">พร้อมขาย (<?= $p['p_stock'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-center">
                                    <label class="relative inline-flex items-center cursor-pointer justify-center">
                                        <input type="checkbox" class="sr-only peer" onchange="window.location.href='?toggle_status=<?= $p['status'] ?>&id=<?= $p['p_id'] ?>'" <?= $p['status'] ? 'checked' : '' ?>>
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                                    </label>
                                </td>
                                
                                <td class="px-6 py-4 text-right pr-8">
                                    <button onclick="openEditModal(<?= $p['p_id'] ?>, '<?= htmlspecialchars($p['p_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['p_sku'], ENT_QUOTES) ?>', <?= $p['c_id'] ?>, <?= $p['p_price'] ?>, <?= $p['p_stock'] ?>, '<?= htmlspecialchars(str_replace(array("\r", "\n"), array('\r', '\n'), $p['p_detail'] ?? ''), ENT_QUOTES) ?>', '<?= $img ?>')" class="w-9 h-9 rounded-full text-gray-400 hover:text-blue-500 hover:bg-blue-50 transition-colors inline-flex items-center justify-center mr-1"><span class="material-icons-round text-[20px]">edit</span></button>
                                    <button onclick="confirmDelete(<?= $p['p_id'] ?>)" class="w-9 h-9 rounded-full text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors inline-flex items-center justify-center"><span class="material-icons-round text-[20px]">delete</span></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="addProductModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300 py-10">
    <div class="bg-white rounded-[2rem] w-full max-w-5xl h-full max-h-[90vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-white">
        
        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-800">เพิ่มสินค้าใหม่</h2>
            </div>
            <button type="button" onclick="closeModal('addProductModal')" class="w-9 h-9 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 hover:border-red-100 transition-colors shadow-sm">
                <span class="material-icons-round text-[20px]">close</span>
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="flex-1 overflow-hidden flex flex-col md:flex-row">
            <input type="hidden" name="action" value="add_product">
            
            <div class="w-full md:w-2/5 p-8 border-r border-gray-100 bg-pink-50/30 overflow-y-auto color-scroll">
                <h3 class="font-bold text-gray-800 mb-5 flex items-center gap-2 text-lg"><span class="material-icons-round text-primary bg-white p-1.5 rounded-xl shadow-sm">collections</span> รูปภาพสินค้า</h3>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 mb-2">รูปภาพหลัก (ปก)</label>
                    <div class="w-full aspect-square border-2 border-dashed border-pink-300 rounded-3xl bg-white flex flex-col items-center justify-center relative hover:border-primary transition-colors cursor-pointer overflow-hidden group shadow-sm">
                        <input type="file" name="main_image" id="mainImageInput" accept="image/*" required class="absolute inset-0 opacity-0 cursor-pointer z-10" onchange="previewMainImage(this)">
                        <img id="mainImagePreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                        <div id="mainImagePlaceholder" class="text-center group-hover:scale-110 transition-transform">
                            <div class="w-16 h-16 bg-pink-50 text-primary rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm"><span class="material-icons-round text-3xl">add_photo_alternate</span></div>
                            <span class="text-sm font-bold text-primary">คลิกอัปโหลดรูปปก</span>
                        </div>
                        <button type="button" id="removeMainImageBtn" onclick="removeMainImage(event)" class="hidden absolute top-3 right-3 z-20 w-9 h-9 bg-white/90 backdrop-blur-sm text-red-500 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors shadow-md">
                            <span class="material-icons-round text-[18px]">close</span>
                        </button>
                    </div>
                </div>

                <div class="mt-6">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-gray-500">รูปภาพเพิ่มเติม (Gallery)</label>
                        <span class="text-[10px] text-primary bg-white px-2 py-1 border border-pink-100 rounded-full font-bold shadow-sm">อัปโหลดได้ไม่จำกัด</span>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3" id="galleryPreviewContainer">
                        <label id="addGalleryBtn" class="aspect-square border-2 border-dashed border-pink-300 rounded-2xl bg-white flex flex-col items-center justify-center cursor-pointer hover:border-primary hover:text-primary transition-all text-primary/60 hover:bg-pink-50 shadow-sm">
                            <span class="material-icons-round text-3xl">add_photo_alternate</span>
                            <span class="text-[10px] font-bold mt-1">เพิ่มรูป</span>
                            <input type="file" multiple accept="image/*" class="hidden" id="galleryInput">
                        </label>
                        <input type="file" name="gallery_images[]" multiple class="hidden" id="realGalleryInput">
                    </div>
                </div>
            </div>

            <div class="w-full md:w-3/5 p-8 overflow-y-auto color-scroll pb-24 relative bg-white">
                <h3 class="font-bold text-gray-800 mb-5 flex items-center gap-2 text-lg"><span class="material-icons-round text-blue-500 bg-blue-50 p-1.5 rounded-xl shadow-sm">description</span> รายละเอียดทั่วไป</h3>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ชื่อสินค้า <span class="text-red-500">*</span></label>
                        <input type="text" name="p_name" required placeholder="เช่น เซรั่มหน้าใส Lumina Glow" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all shadow-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รหัสสินค้า (SKU)</label>
                            <input type="text" name="p_sku" placeholder="LM-001" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-mono text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">หมวดหมู่ <span class="text-red-500">*</span></label>
                            <select name="c_id" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all appearance-none shadow-sm cursor-pointer">
                                <option value="">เลือกหมวดหมู่...</option>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['c_id'] ?>"><?= $c['c_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ราคา (บาท) <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400 font-bold">฿</span>
                                <input type="number" name="p_price" required min="0" placeholder="0.00" class="w-full pl-10 pr-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-bold text-primary shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">สต็อก <span class="text-red-500">*</span></label>
                            <input type="number" name="p_stock" required min="0" placeholder="0" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รายละเอียด</label>
                        <textarea name="p_detail" rows="4" placeholder="กรอกคุณสมบัติ, วิธีใช้, ส่วนผสม..." class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all resize-none shadow-sm"></textarea>
                    </div>

                    <div class="pt-5 border-t border-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2 text-lg"><span class="material-icons-round text-purple-500 bg-purple-50 p-1.5 rounded-xl shadow-sm">palette</span> ตัวเลือกสี</h3>
                            <button type="button" onclick="addColorRow()" class="text-xs bg-white border border-purple-200 text-purple-600 font-bold px-4 py-2 rounded-full hover:bg-purple-50 hover:border-purple-300 flex items-center gap-1 transition-all shadow-sm">
                                <span class="material-icons-round text-[16px]">add</span> เพิ่มสี
                            </button>
                        </div>
                        <div id="colorContainer" class="space-y-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 right-0 p-5 bg-white/90 backdrop-blur-md border-t border-gray-100 flex justify-end gap-3 rounded-b-[2rem] z-10 shadow-[0_-10px_30px_rgba(0,0,0,0.02)]">
                <button type="button" onclick="closeModal('addProductModal')" class="px-8 py-3 rounded-full bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition-colors border border-gray-200">ยกเลิก</button>
                <button type="submit" class="px-8 py-3 rounded-full bg-primary text-white font-bold hover:bg-pink-600 shadow-lg shadow-primary/40 transition-all transform hover:-translate-y-0.5">บันทึกสินค้าใหม่</button>
            </div>
        </form>
    </div>
</div>

<div id="editProductModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300 py-10">
    <div class="bg-white rounded-[2rem] w-full max-w-4xl h-auto max-h-[90vh] shadow-2xl flex flex-col overflow-hidden modal-content transform scale-95 transition-transform duration-300 border border-white">
        
        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h2 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2"><span class="material-icons-round text-blue-500">edit_note</span> แก้ไขข้อมูลสินค้า</h2>
            <button type="button" onclick="closeModal('editProductModal')" class="w-9 h-9 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 hover:bg-red-50 shadow-sm transition-colors">
                <span class="material-icons-round text-[20px]">close</span>
            </button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto p-8 color-scroll pb-28 bg-white">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="edit_p_id" id="edit_p_id">
            
            <div class="flex flex-col md:flex-row gap-8">
                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">เปลี่ยนรูปปก (ไม่บังคับ)</label>
                    <div class="w-full aspect-square border-2 border-dashed border-gray-300 rounded-3xl bg-gray-50 flex flex-col items-center justify-center relative hover:border-primary transition-colors cursor-pointer overflow-hidden group shadow-sm">
                        <input type="file" name="main_image" id="editMainImageInput" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer z-10" onchange="previewEditMainImage(this)">
                        <img id="editImagePreview" src="" class="absolute inset-0 w-full h-full object-cover hidden z-0">
                        <div id="editImagePlaceholder" class="text-center group-hover:scale-110 transition-transform">
                            <div class="w-14 h-14 bg-white text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm border border-gray-100"><span class="material-icons-round text-2xl">upload</span></div>
                            <span class="text-xs font-bold text-gray-500">คลิกอัปโหลดรูปใหม่</span>
                        </div>
                        <button type="button" id="removeEditImageBtn" onclick="removeEditMainImage(event)" class="hidden absolute top-3 right-3 z-20 w-9 h-9 bg-white/90 backdrop-blur-sm text-red-500 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors shadow-md">
                            <span class="material-icons-round text-[18px]">close</span>
                        </button>
                    </div>
                </div>

                <div class="w-full md:w-2/3 space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ชื่อสินค้า</label>
                        <input type="text" name="p_name" id="edit_p_name" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all shadow-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รหัสสินค้า</label>
                            <input type="text" name="p_sku" id="edit_p_sku" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-mono text-sm shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">หมวดหมู่</label>
                            <select name="c_id" id="edit_c_id" required class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all appearance-none shadow-sm cursor-pointer">
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['c_id'] ?>"><?= $c['c_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">ราคา (บาท)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-400 font-bold">฿</span>
                                <input type="number" name="p_price" id="edit_p_price" required min="0" class="w-full pl-10 pr-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all font-bold text-primary shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">สต็อก</label>
                            <input type="number" name="p_stock" id="edit_p_stock" required min="0" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1.5 ml-1">รายละเอียด</label>
                        <textarea name="p_detail" id="edit_p_detail" rows="4" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/30 outline-none transition-all resize-none shadow-sm"></textarea>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-0 left-0 right-0 p-5 bg-white/90 backdrop-blur-md border-t border-gray-100 flex justify-end gap-3 rounded-b-[2rem] z-10 shadow-[0_-10px_30px_rgba(0,0,0,0.02)]">
                <button type="button" onclick="closeModal('editProductModal')" class="px-8 py-3 rounded-full bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition-colors border border-gray-200">ยกเลิก</button>
                <button type="submit" class="px-8 py-3 rounded-full bg-blue-500 text-white font-bold hover:bg-blue-600 shadow-lg shadow-blue-500/40 transition-all transform hover:-translate-y-0.5">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        const m = document.getElementById(id);
        m.classList.remove('hidden'); setTimeout(() => { m.classList.remove('opacity-0'); m.querySelector('.modal-content').classList.remove('scale-95'); }, 10);
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        m.classList.add('opacity-0'); m.querySelector('.modal-content').classList.add('scale-95');
        setTimeout(() => m.classList.add('hidden'), 300);
    }

    // โยนข้อมูลเก่าเข้าช่องกรอกสำหรับแก้ไข
    function openEditModal(id, name, sku, cid, price, stock, detail, imgUrl) {
        document.getElementById('edit_p_id').value = id;
        document.getElementById('edit_p_name').value = name;
        document.getElementById('edit_p_sku').value = sku;
        document.getElementById('edit_c_id').value = cid;
        document.getElementById('edit_p_price').value = price;
        document.getElementById('edit_p_stock').value = stock;
        document.getElementById('edit_p_detail').value = detail.replace(/\\n/g, "\n").replace(/\\r/g, "\r");
        
        document.getElementById('editMainImageInput').value = ''; 
        if (imgUrl && !imgUrl.includes('placeholder')) {
            document.getElementById('editImagePreview').src = imgUrl;
            document.getElementById('editImagePreview').classList.remove('hidden');
            document.getElementById('editImagePlaceholder').classList.add('hidden');
            document.getElementById('removeEditImageBtn').classList.remove('hidden');
        } else {
            document.getElementById('editImagePreview').classList.add('hidden');
            document.getElementById('editImagePlaceholder').classList.remove('hidden');
            document.getElementById('removeEditImageBtn').classList.add('hidden');
        }

        openModal('editProductModal');
    }

    function removeEditMainImage(event) {
        event.preventDefault();
        document.getElementById('editMainImageInput').value = ''; 
        document.getElementById('editImagePreview').src = '';
        document.getElementById('editImagePreview').classList.add('hidden');
        document.getElementById('editImagePlaceholder').classList.remove('hidden');
        document.getElementById('removeEditImageBtn').classList.add('hidden');
    }

    function previewEditMainImage(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('editImagePreview').src = e.target.result;
                document.getElementById('editImagePreview').classList.remove('hidden');
                document.getElementById('editImagePlaceholder').classList.add('hidden');
                document.getElementById('removeEditImageBtn').classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?', text: "สินค้านี้และรูปภาพทั้งหมดจะถูกลบถาวร!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#9CA3AF',
            confirmButtonText: 'ลบเลย!', cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-full px-6', cancelButton: 'rounded-full px-6' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = '?delete=' + id;
        });
    }

    <?php if (isset($_SESSION['success_msg'])): ?>
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?= $_SESSION['success_msg'] ?>', confirmButtonColor: '#ec2d88', customClass: { popup: 'rounded-3xl' }});
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>

    function previewMainImage(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('mainImagePreview').src = e.target.result;
                document.getElementById('mainImagePreview').classList.remove('hidden');
                document.getElementById('mainImagePlaceholder').classList.add('hidden');
                document.getElementById('removeMainImageBtn').classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeMainImage(event) {
        event.preventDefault(); 
        document.getElementById('mainImageInput').value = '';
        document.getElementById('mainImagePreview').src = '';
        document.getElementById('mainImagePreview').classList.add('hidden');
        document.getElementById('mainImagePlaceholder').classList.remove('hidden');
        document.getElementById('removeMainImageBtn').classList.add('hidden');
    }

    // จัดการ Gallery อัปโหลดหลายรูปไม่ค้าง 100%
    let galleryDataTransfer = new DataTransfer(); 
    const galleryInput = document.getElementById('galleryInput');
    const realGalleryInput = document.getElementById('realGalleryInput');
    const container = document.getElementById('galleryPreviewContainer');
    const addBtn = document.getElementById('addGalleryBtn');

    if (galleryInput) {
        galleryInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    galleryDataTransfer.items.add(files[i]);
                }
                refreshGalleryUI();
            }
            this.value = ''; 
        });
    }

    function refreshGalleryUI() {
        container.querySelectorAll('.gallery-preview-item').forEach(el => el.remove());
        const files = galleryDataTransfer.files;
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const objectUrl = URL.createObjectURL(file);
            
            const div = document.createElement('div');
            div.className = 'gallery-preview-item aspect-square rounded-2xl overflow-hidden border border-gray-200 shadow-sm relative group bg-white';
            div.innerHTML = `
                <img src="${objectUrl}" class="w-full h-full object-cover" onload="URL.revokeObjectURL(this.src)">
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-[2px]">
                    <button type="button" onclick="removeGalleryImage(${i})" class="w-9 h-9 bg-white text-red-500 rounded-full flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors transform hover:scale-110 shadow-md">
                        <span class="material-icons-round text-[18px]">delete</span>
                    </button>
                </div>
            `;
            container.insertBefore(div, addBtn);
        }
        if (realGalleryInput) realGalleryInput.files = galleryDataTransfer.files;
    }

    window.removeGalleryImage = function(indexToRemove) {
        const newDt = new DataTransfer();
        const files = galleryDataTransfer.files;
        for (let i = 0; i < files.length; i++) {
            if (i !== indexToRemove) newDt.items.add(files[i]);
        }
        galleryDataTransfer = newDt;
        refreshGalleryUI();
    }

    let colorIndex = 1;
    function addColorRow() {
        const colorContainer = document.getElementById('colorContainer');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 p-2 bg-white border border-gray-200 rounded-2xl shadow-sm hover:border-purple-300 transition-colors';
        row.innerHTML = `
            <input type="color" name="color_hexes[]" value="#ec2d88" class="w-12 h-12 rounded-xl cursor-pointer border-0 p-0 bg-transparent flex-shrink-0 shadow-sm">
            <input type="text" name="color_names[]" placeholder="ชื่อสี เช่น #01 W -'Bout to slay" class="flex-1 bg-transparent border-none text-sm focus:ring-0 outline-none p-0 text-gray-700 font-medium ml-2">
            <button type="button" onclick="this.parentElement.remove()" class="w-9 h-9 rounded-full bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition-colors flex-shrink-0 mr-1">
                <span class="material-icons-round text-[18px]">close</span>
            </button>
        `;
        colorContainer.appendChild(row);
        colorIndex++;
    }
    document.addEventListener("DOMContentLoaded", addColorRow);
</script>
</body>
</html>