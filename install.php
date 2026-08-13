<?php
session_start();

// Handle AJAX requests
if (isset($_GET['step'])) {
    header('Content-Type: application/json');
    $step = $_GET['step'];

    try {
        if ($step === '1') {
            // Step 1: Save Configuration
            $data = json_decode(file_get_contents('php://input'), true);

            $configContent = "<?php\n\n\$config = [\n";
            $configContent .= "    'app' => [\n";
            $configContent .= "        'title' => '" . addslashes($data['title']) . "',\n";
            $configContent .= "        'url' => '" . addslashes($data['url']) . "',\n";
            $configContent .= "        'admin_id' => '" . addslashes($data['admin_id']) . "'\n";
            $configContent .= "    ],\n";

            $configContent .= "    'db' => [\n";
            $configContent .= "        'host' => '" . addslashes($data['db_host']) . "',\n";
            $configContent .= "        'dbname' => '" . addslashes($data['db_name']) . "',\n";
            $configContent .= "        'user' => '" . addslashes($data['db_user']) . "',\n";
            $configContent .= "        'pass' => '" . addslashes($data['db_pass']) . "',\n";
            $configContent .= "        'charset' => 'utf8mb4'\n";
            $configContent .= "    ],\n";

            $configContent .= "    's3' => [\n";
            $configContent .= "        'endpoint' => '" . addslashes($data['s3_endpoint']) . "',\n";
            $configContent .= "        'region' => '" . addslashes($data['s3_region']) . "',\n";
            $configContent .= "        'key' => '" . addslashes($data['s3_key']) . "',\n";
            $configContent .= "        'secret' => '" . addslashes($data['s3_secret']) . "',\n";
            $configContent .= "        'bucket' => '" . addslashes($data['s3_bucket']) . "'\n";
            $configContent .= "    ],\n";

            $configContent .= "    'telegram' => [\n";
            $configContent .= "        'bot_token' => '" . addslashes($data['tg_bot_token']) . "'\n";
            $configContent .= "    ]\n";
            $configContent .= "];\n";

            if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
                throw new Exception("Failed to write config.php");
            }

            // Set session so step 4 can use it
            $_SESSION['app_title'] = $data['title'];
            echo json_encode(['success' => true]);
            exit;
        }

        else if ($step === '2') {
            // Step 2: Database Setup
            require_once __DIR__ . '/config.php';
            require_once __DIR__ . '/db.php';

            $db = getDbConnection();
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            if ($sql === false) {
                throw new Exception("Could not read schema.sql");
            }

            // Execute schema
            $db->exec($sql);

            // Also run patches if they are not included in schema.sql yet
            if (file_exists(__DIR__ . '/patch_schema.sql')) {
                try {
                    $db->exec(file_get_contents(__DIR__ . '/patch_schema.sql'));
                } catch (\PDOException $e) {
                    // Ignore duplicate column errors if patch is already applied
                    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                        throw $e;
                    }
                }
            }

            echo json_encode(['success' => true]);
            exit;
        }

        else if ($step === '3') {
            // Step 3: Dependencies
            $composerPath = __DIR__ . '/composer.phar';
            if (!file_exists($composerPath)) {
                file_put_contents($composerPath, file_get_contents('https://getcomposer.org/download/latest-stable/composer.phar'));
            }

            // Use shell_exec to run composer install
            $output = shell_exec('php composer.phar install --no-dev --optimize-autoloader 2>&1');

            echo json_encode(['success' => true, 'output' => $output]);
            exit;
        }

        else if ($step === '4') {
            // Step 4: Replace Titles and Finalize

            // We read it from config.php directly instead of session, just in case session failed
            require_once __DIR__ . '/config.php';
            global $config;
            $title = $config['app']['title'] ?? 'Astral-Tele';

            // Files to modify
            $files = [
                'index.html', 'admin.html', 'detail.html', 'library.html',
                'notifications.html', 'profile.html', 'quest.html', 'reader.html',
                'js/detail.js'
            ];

            foreach ($files as $file) {
                $filePath = __DIR__ . '/' . $file;
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    $content = str_replace('Astral-Tele', $title, $content);
                    file_put_contents($filePath, $content);
                }
            }

            // Delete self securely after request finishes
            register_shutdown_function(function() {
                @unlink(__FILE__);
            });

            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-8">
        <h1 class="text-3xl font-bold mb-6 text-center">Installation</h1>

        <div id="step1" class="step">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2">Step 1: Configuration</h2>
            <form id="configForm" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-1">Website Title</label>
                        <input type="text" id="title" required class="w-full border rounded p-2" placeholder="My Comic Site">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">Website URL</label>
                        <input type="url" id="url" required class="w-full border rounded p-2" placeholder="https://example.com">
                    </div>
                </div>

                <h3 class="font-bold mt-4 pt-4 border-t">Database Setup</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-1">DB Host</label>
                        <input type="text" id="db_host" required class="w-full border rounded p-2" value="127.0.0.1">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">DB Name</label>
                        <input type="text" id="db_name" required class="w-full border rounded p-2" value="astral_tele">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">DB User</label>
                        <input type="text" id="db_user" required class="w-full border rounded p-2" value="root">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">DB Pass</label>
                        <input type="password" id="db_pass" class="w-full border rounded p-2">
                    </div>
                </div>

                <h3 class="font-bold mt-4 pt-4 border-t">S3 Compatible Storage Setup</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-bold mb-1">S3 Endpoint</label>
                        <input type="url" id="s3_endpoint" required class="w-full border rounded p-2" placeholder="https://s3.example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">S3 Region</label>
                        <input type="text" id="s3_region" required class="w-full border rounded p-2" value="us-east-1">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">S3 Bucket</label>
                        <input type="text" id="s3_bucket" required class="w-full border rounded p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">S3 Key</label>
                        <input type="text" id="s3_key" required class="w-full border rounded p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">S3 Secret</label>
                        <input type="password" id="s3_secret" required class="w-full border rounded p-2">
                    </div>
                </div>

                <h3 class="font-bold mt-4 pt-4 border-t">Telegram Setup</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold mb-1">Telegram Bot Token</label>
                        <input type="text" id="tg_bot_token" required class="w-full border rounded p-2">
                    </div>
                    <div>
                        <label class="block text-sm font-bold mb-1">Admin Access (Telegram User ID)</label>
                        <input type="text" id="admin_id" required class="w-full border rounded p-2" placeholder="123456789">
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save & Next</button>
                </div>
            </form>
        </div>

        <div id="progress" class="hidden">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2">Installation Progress</h2>
            <ul class="space-y-3" id="logList">
                <li id="log2" class="text-gray-500">⏳ Setup Database...</li>
                <li id="log3" class="text-gray-500">⏳ Install Dependencies (Composer)...</li>
                <li id="log4" class="text-gray-500">⏳ Finalize & Cleanup...</li>
            </ul>
            <div id="finalMessage" class="hidden mt-6 p-4 bg-green-100 text-green-700 rounded text-center font-bold">
                Installation Complete! <br><a href="index.html" class="underline text-blue-600 mt-2 inline-block">Go to App</a>
            </div>
            <div id="errorMessage" class="hidden mt-6 p-4 bg-red-100 text-red-700 rounded text-center">
            </div>
        </div>
    </div>

    <script>
        document.getElementById('configForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const data = {
                title: document.getElementById('title').value,
                url: document.getElementById('url').value,
                db_host: document.getElementById('db_host').value,
                db_name: document.getElementById('db_name').value,
                db_user: document.getElementById('db_user').value,
                db_pass: document.getElementById('db_pass').value,
                s3_endpoint: document.getElementById('s3_endpoint').value,
                s3_region: document.getElementById('s3_region').value,
                s3_bucket: document.getElementById('s3_bucket').value,
                s3_key: document.getElementById('s3_key').value,
                s3_secret: document.getElementById('s3_secret').value,
                tg_bot_token: document.getElementById('tg_bot_token').value,
                admin_id: document.getElementById('admin_id').value
            };

            document.getElementById('step1').classList.add('hidden');
            document.getElementById('progress').classList.remove('hidden');

            try {
                // Step 1: Save
                let res = await fetch('?step=1', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                let result = await res.json();
                if (!result.success) throw new Error(result.error);

                // Step 2: DB
                updateLog('log2', '⏳ Running Database Setup...', 'text-blue-500');
                res = await fetch('?step=2');
                result = await res.json();
                if (!result.success) throw new Error(result.error);
                updateLog('log2', '✅ Database Setup Complete', 'text-green-600 font-bold');

                // Step 3: Composer
                updateLog('log3', '⏳ Installing Composer Dependencies (this may take a minute)...', 'text-blue-500');
                res = await fetch('?step=3');
                result = await res.json();
                if (!result.success) throw new Error(result.error);
                console.log(result.output);
                updateLog('log3', '✅ Dependencies Installed', 'text-green-600 font-bold');

                // Step 4: Finalize
                updateLog('log4', '⏳ Finalizing and cleaning up...', 'text-blue-500');
                res = await fetch('?step=4');
                result = await res.json();
                if (!result.success) throw new Error(result.error);
                updateLog('log4', '✅ Finalized. Installer deleted.', 'text-green-600 font-bold');

                document.getElementById('finalMessage').classList.remove('hidden');

            } catch (err) {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.textContent = 'Error: ' + err.message;
                errorDiv.classList.remove('hidden');
            }
        });

        function updateLog(id, text, classes) {
            const el = document.getElementById(id);
            el.textContent = text;
            el.className = classes;
        }
    </script>
</body>
</html>