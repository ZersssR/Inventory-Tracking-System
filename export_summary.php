<?php
// Include database connection
include('database.php');

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=monthly_report.xls");
header("Pragma: no-cache");
header("Expires: 0");

$today = date("Y-m-d");

// SQL query to fetch the total quantity per loadout location and TEC expiry categories
$sql_offshore = "SELECT 
                    loadout_location,
                    COUNT(CASE 
                        WHEN STR_TO_DATE(tec_expiry, '%Y-%m-%d') < '$today' THEN description
                        ELSE NULL 
                    END) AS expired,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 28 
                          AND STR_TO_DATE(tec_expiry, '%Y-%m-%d') >= '$today' THEN description 
                        ELSE NULL 
                    END) AS less_than_4_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 28 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 56 THEN description
                        ELSE NULL 
                    END) AS less_than_8_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 56 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 84 THEN description 
                        ELSE NULL 
                    END) AS less_than_12_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 84 THEN description 
                        ELSE NULL 
                    END) AS valid
                FROM product
                WHERE loadout_location IS NOT NULL AND loadout_location <> ''  
                GROUP BY loadout_location
                HAVING (expired + less_than_4_weeks + less_than_8_weeks + less_than_12_weeks + valid) > 0  
                ORDER BY loadout_location";

$result_offshore = $conn->query($sql_offshore);

// Fetch Onshore Summary (New Query)
$sql_onshore = "SELECT 
                    COUNT(CASE 
                        WHEN STR_TO_DATE(tec_expiry, '%Y-%m-%d') < '$today' THEN description
                        ELSE NULL 
                    END) AS expired,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 28 
                          AND STR_TO_DATE(tec_expiry, '%Y-%m-%d') >= '$today' THEN description 
                        ELSE NULL 
                    END) AS less_than_4_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 28 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 56 THEN description
                        ELSE NULL 
                    END) AS less_than_8_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 56 
                          AND DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') < 84 THEN description 
                        ELSE NULL 
                    END) AS less_than_12_weeks,
                    COUNT(CASE 
                        WHEN DATEDIFF(STR_TO_DATE(tec_expiry, '%Y-%m-%d'), '$today') >= 84 THEN description 
                        ELSE NULL 
                    END) AS valid
                FROM product
                WHERE (action_notice_no IS NULL OR action_notice_no = '') 
                  AND (loadout_location IS NULL OR loadout_location = '') 
                  AND (loadout_date IS NULL OR loadout_date = '')";

$result_onshore = $conn->query($sql_onshore);
$row_onshore = $result_onshore->fetch_assoc();

// Output Headers
echo "Loadout Location\tExpired\tLess than 4 Weeks\tLess than 8 Weeks\tLess than 12 Weeks\tValid (More than 12 Weeks)\tTotal\n";

// Output Loadout Items data (Offshore)
while ($row = $result_offshore->fetch_assoc()) {
    $total = $row['expired'] + $row['less_than_4_weeks'] + $row['less_than_8_weeks'] + $row['less_than_12_weeks'] + $row['valid'];
    echo "{$row['loadout_location']}\t{$row['expired']}\t{$row['less_than_4_weeks']}\t{$row['less_than_8_weeks']}\t{$row['less_than_12_weeks']}\t{$row['valid']}\t$total\n";
}

// Output Onshore Summary
echo "Onshore\t{$row_onshore['expired']}\t{$row_onshore['less_than_4_weeks']}\t{$row_onshore['less_than_8_weeks']}\t{$row_onshore['less_than_12_weeks']}\t{$row_onshore['valid']}\t" .
    ($row_onshore['expired'] + $row_onshore['less_than_4_weeks'] + $row_onshore['less_than_8_weeks'] + $row_onshore['less_than_12_weeks'] + $row_onshore['valid']) . "\n";

?>
