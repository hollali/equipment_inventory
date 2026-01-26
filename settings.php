<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Parliament ICT</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <?php include './sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main id="mainContent" class="flex-1 transition-all duration-300 ml-64 p-8">

        <!-- HEADER -->
        <header class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Settings</h1>
                <p class="text-slate-600 mt-1">System preferences and configuration</p>
            </div>
        </header>

        <!-- SUCCESS MESSAGE -->
        <div id="successMessage" class="hidden mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg">
            <div class="flex items-center gap-2">
                <span class="text-green-600">✓</span>
                <p class="text-green-800 font-medium" id="messageText"></p>
            </div>
        </div>

        <!-- ORGANIZATION DETAILS -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">Organization Details</h2>
                <p class="text-slate-600 text-sm mt-1">Set the directorate information used in reports</p>
            </div>

            <form id="orgForm" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Organization Name</label>
                        <input type="text" id="orgName"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="Parliament of Ghana ICT Directorate">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Default Report Contact</label>
                        <input type="email" id="orgContact"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="ict@parliament.gov.gh">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Report Footer</label>
                        <input type="text" id="orgFooter"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="Confidential - Internal Use Only">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Default Assignment Type</label>
                        <select id="orgAssignment"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
                            <option>MP</option>
                            <option>Staff</option>
                            <option>Office</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm font-medium">
                        Save Changes
                    </button>
                </div>
            </form>
        </section>

        <!-- INVENTORY PREFERENCES -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">Inventory Preferences</h2>
                <p class="text-slate-600 text-sm mt-1">Configure default statuses and alerts</p>
            </div>

            <form id="prefsForm" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Default Status</label>
                        <select id="prefsStatus"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
                            <option>In Use</option>
                            <option>Store</option>
                            <option>Faulty</option>
                            <option>Retired</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Retirement Threshold
                            (months)</label>
                        <input type="number" id="prefsThreshold"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="36" min="1">
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-lg">
                        <input type="checkbox" id="prefsEmailAlerts" checked
                            class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <label for="prefsEmailAlerts" class="text-sm font-medium text-slate-700">Email Alerts</label>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-slate-50 rounded-lg">
                        <input type="checkbox" id="prefsCompliance"
                            class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                        <label for="prefsCompliance" class="text-sm font-medium text-slate-700">Compliance
                            Reminders</label>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm font-medium">
                        Update Preferences
                    </button>
                </div>
            </form>
        </section>

    </main>

    <!-- JS -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('toggleSidebar');

        // Handle sidebar collapse
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            mainContent.classList.replace('ml-64', 'ml-20');
        }

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');

            if (sidebar.classList.contains('collapsed')) {
                mainContent.classList.replace('ml-64', 'ml-20');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                mainContent.classList.replace('ml-20', 'ml-64');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        });

        // In-memory storage for settings
        let settings = {
            org: {
                name: "Parliament of Ghana ICT Directorate",
                contact: "ict@parliament.gov.gh",
                footer: "Confidential - Internal Use Only",
                assignment: "MP"
            },
            prefs: {
                status: "In Use",
                threshold: 36,
                emailAlerts: true,
                compliance: false
            }
        };

        function showMessage(text) {
            const msgEl = document.getElementById('successMessage');
            const msgText = document.getElementById('messageText');
            msgText.textContent = text;
            msgEl.classList.remove('hidden');
            setTimeout(() => {
                msgEl.classList.add('hidden');
            }, 3000);
        }

        // Organization form handler
        document.getElementById('orgForm').addEventListener('submit', (e) => {
            e.preventDefault();
            settings.org = {
                name: document.getElementById('orgName').value,
                contact: document.getElementById('orgContact').value,
                footer: document.getElementById('orgFooter').value,
                assignment: document.getElementById('orgAssignment').value
            };
            showMessage('Organization settings saved successfully!');
        });

        // Preferences form handler
        document.getElementById('prefsForm').addEventListener('submit', (e) => {
            e.preventDefault();
            settings.prefs = {
                status: document.getElementById('prefsStatus').value,
                threshold: parseInt(document.getElementById('prefsThreshold').value),
                emailAlerts: document.getElementById('prefsEmailAlerts').checked,
                compliance: document.getElementById('prefsCompliance').checked
            };
            showMessage('Inventory preferences updated successfully!');
        });
    </script>

</body>

</html>