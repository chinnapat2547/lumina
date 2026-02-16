<?php
// เชื่อมต่อฐานข้อมูล
require_once '../config/connectdbuser.php'; 

if (isset($_GET['q'])) {
    $search = "%" . trim($_GET['q']) . "%"; // ค้นหาคำที่มีส่วนประกอบของตัวอักษรนั้นๆ
    
    // ดึงข้อมูลสินค้าที่ชื่อตรงกับที่ค้นหา (จำกัด 5 รายการเพื่อไม่ให้รก)
    $sql = "SELECT p_id, p_name, p_price, p_image FROM `product` WHERE p_name LIKE ? LIMIT 5";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // เช็คว่ามีรูปไหม
            $img = (!empty($row['p_image']) && file_exists("../uploads/products/" . $row['p_image'])) 
                    ? "../uploads/products/" . $row['p_image'] 
                    : "https://via.placeholder.com/150x150.png?text=No+Image";
            
            // สร้างบล็อก HTML ส่งกลับไปโชว์
            echo '
            <a href="productdetail.php?id='.$row['p_id'].'" class="flex items-center gap-3 p-3 hover:bg-pink-50 dark:hover:bg-gray-700 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-none">
                <div class="w-12 h-12 bg-white dark:bg-gray-800 rounded-lg overflow-hidden flex-shrink-0 shadow-sm p-0.5">
                    <img src="'.$img.'" class="w-full h-full object-cover rounded-md" alt="'.htmlspecialchars($row['p_name']).'">
                </div>
                <div class="flex-1 overflow-hidden">
                    <h4 class="text-sm font-bold text-gray-800 dark:text-white truncate">'.htmlspecialchars($row['p_name']).'</h4>
                    <p class="text-xs font-medium text-primary mt-0.5">฿'.number_format($row['p_price']).'</p>
                </div>
            </a>';
        }
    } else {
        // ถ้าไม่พบสินค้า
        echo '<div class="p-4 text-center text-sm font-medium text-gray-500 dark:text-gray-400">ไม่มีสินค้าที่คุณค้นหา 😥</div>';
    }
    mysqli_stmt_close($stmt);
}
?>