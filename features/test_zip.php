<?php
echo extension_loaded('zip') ? "✅ Zip extension is ENABLED\n" : "❌ Zip extension is NOT enabled\n";
echo "\nAll loaded extensions:\n";
print_r(get_loaded_extensions());
