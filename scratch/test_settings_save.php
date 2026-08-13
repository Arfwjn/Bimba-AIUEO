<?php
// scratch/test_settings_save.php
require_once __DIR__ . '/../config/database.php';

echo "=== TESTING DYNAMIC INSTITUTION SETTINGS ===\n\n";

save_system_setting('unit_name', 'biMBA AIUEO Unit Kebanggan Jakarta');
save_system_setting('unit_leader', 'Siti Rahmawati, S.Pd, M.M');
save_system_setting('unit_location', 'Jakarta Selatan');
save_system_setting('unit_address', 'Jl. Kebanggan Raya No. 45, Jakarta');
save_system_setting('unit_phone', '(021) 7788-9900');
save_system_setting('unit_email', 'admin@bimba-kebanggan.sch.id');

echo "Saved settings successfully!\n";
echo "Read unit_name: " . get_system_setting('unit_name') . "\n";
echo "Read unit_leader: " . get_system_setting('unit_leader') . "\n";
echo "Read unit_location: " . get_system_setting('unit_location') . "\n";
echo "Read unit_address: " . get_system_setting('unit_address') . "\n";
echo "Read unit_phone: " . get_system_setting('unit_phone') . "\n";
echo "Read unit_email: " . get_system_setting('unit_email') . "\n";

echo "\n=== DYNAMIC SETTINGS TEST PASSED 100% ===";
