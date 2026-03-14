document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.bmi-calculator-wrap');
    if (!container) return;

    // --- Diet Presets ---
    const DIET_PRESETS = {
        'balanced':     { protein: 30, carbs: 40, fats: 30 },
        'keto':         { protein: 20, carbs: 5,  fats: 75 },
        'paleo':        { protein: 30, carbs: 20, fats: 50 },
        'high-protein': { protein: 40, carbs: 30, fats: 30 },
    };

    // --- i18n helper ---
    const i18n = (typeof bmiCalcI18n !== 'undefined') ? bmiCalcI18n : {};
    function t(key, fallback) { return i18n[key] || fallback || key; }

    // Enhancement 1: Read configurable attributes from container data attributes
    const config = {
        primaryColor: container.dataset.primaryColor || '#0073aa',
        showHistory: container.dataset.showHistory !== 'false',
        showConverter: container.dataset.showConverter !== 'false',
        macroProtein: parseInt(container.dataset.macroProtein) || 30,
        macroCarbs: parseInt(container.dataset.macroCarbs) || 40,
        macroFats: parseInt(container.dataset.macroFats) || 30,
        dietType: container.dataset.dietType || 'balanced',
    };

    // --- Phase 3: Accessibility & Contrast Helper ---
    function getContrastYIQ(hexcolor) {
        hexcolor = hexcolor.replace("#", "");
        if (hexcolor.length === 3) {
            hexcolor = hexcolor.split('').map(char => char + char).join('');
        }
        const r = parseInt(hexcolor.substr(0, 2), 16);
        const g = parseInt(hexcolor.substr(2, 2), 16);
        const b = parseInt(hexcolor.substr(4, 2), 16);
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? 'black' : 'white';
    }

    // Apply high-contrast text color to buttons using primary color
    const contrastColor = getContrastYIQ(config.primaryColor);
    container.style.setProperty('--bmi-accent-text', contrastColor);

    // Enhancement 4: Check for logged-in WP user
    const wpUser = window.BMI_WP_USER || null;

    // If logged-in user, auto-fill name and hide name/surname fields
    if (wpUser) {
        const nameInput = container.querySelector('#bmi-name');
        const surnameInput = container.querySelector('#bmi-surname');
        if (nameInput) {
            nameInput.value = wpUser.name || '';
            nameInput.closest('.form-group').style.display = 'none';
        }
        if (surnameInput) {
            surnameInput.value = '';
            surnameInput.closest('.form-group').style.display = 'none';
        }
    }

    // --- Theme Toggle ---
    const themeBtn = container.querySelector('#bmi-themeBtn');

    if (localStorage.getItem('bmi-theme') === 'dark') {
        container.classList.add('dark-mode');
        themeBtn.innerText = '☀️ ' + t('lightMode', 'Light Mode');
    }

    themeBtn.addEventListener('click', () => {
        container.classList.toggle('dark-mode');
        const isDark = container.classList.contains('dark-mode');
        localStorage.setItem('bmi-theme', isDark ? 'dark' : 'light');
        themeBtn.innerText = isDark ? '☀️ ' + t('lightMode', 'Light Mode') : '🌙 ' + t('darkMode', 'Dark Mode');
    });

    // --- Main Calculator Logic ---
    const calcBtn = container.querySelector('#bmi-calcBtn');
    const clearBtn = container.querySelector('#bmi-clearBtn');
    const resultsArea = container.querySelector('#bmi-resultsArea');
    const historyArea = container.querySelector('#bmi-historyArea');
    const historyBody = container.querySelector('#bmi-historyBody');
    const userSelect = container.querySelector('#bmi-userSelect');
    const chartContainer = container.querySelector('#bmi-chartContainer');
    const exportBtn = container.querySelector('#bmi-exportBtn');
    let bmiChartInstance = null;
    let currentFilteredHistory = [];

    // Enhancement 1: Respect showHistory/showConverter flags
    if (config.showHistory && userSelect) {
        userSelect.addEventListener('change', loadHistory);
    }
    if (exportBtn) {
        exportBtn.addEventListener('click', exportToCSV);
    }

    // Enhancement 4: Load user-specific history if logged in, otherwise load all
    if (wpUser && config.showHistory) {
        loadUserHistory();
    } else if (config.showHistory) {
        loadHistory();
    }

    calcBtn.addEventListener('click', calculateBMI);
    clearBtn.addEventListener('click', clearForm);

    // --- Diet Type Change ---
    const dietTypeSelect = container.querySelector('#bmi-dietType');
    function getCurrentDiet() {
        if (dietTypeSelect && dietTypeSelect.value !== 'balanced') {
            return DIET_PRESETS[dietTypeSelect.value] || DIET_PRESETS['balanced'];
        }
        // If balanced or no select, check if we have custom block config
        return {
            protein: config.macroProtein,
            carbs: config.macroCarbs,
            fats: config.macroFats
        };
    }

    function updateMacroLabels() {
        const diet = getCurrentDiet();
        const proteinLabel = container.querySelector('#bmi-proteinLabel');
        const carbsLabel = container.querySelector('#bmi-carbsLabel');
        const fatsLabel = container.querySelector('#bmi-fatsLabel');
        if (proteinLabel) proteinLabel.innerText = t('protein', 'Protein') + ' (' + diet.protein + '%)';
        if (carbsLabel) carbsLabel.innerText = t('carbs', 'Carbs') + ' (' + diet.carbs + '%)';
        if (fatsLabel) fatsLabel.innerText = t('fats', 'Fats') + ' (' + diet.fats + '%)';
    }

    if (dietTypeSelect) {
        // Set initial value from block attribute
        if (config.dietType && dietTypeSelect.querySelector('option[value="' + config.dietType + '"]')) {
            dietTypeSelect.value = config.dietType;
        }
        dietTypeSelect.addEventListener('change', () => {
            const diet = getCurrentDiet();
            config.macroProtein = diet.protein;
            config.macroCarbs = diet.carbs;
            config.macroFats = diet.fats;
            updateMacroLabels();
            // Recalculate if results are visible
            if (resultsArea && !resultsArea.classList.contains('hidden')) {
                recalcMacros();
            }
        });
    }

    function recalcMacros() {
        const tdeeText = container.querySelector('#bmi-calOutResult').innerText;
        const tdee = parseFloat(tdeeText);
        if (isNaN(tdee) || tdee <= 0) return;
        const diet = getCurrentDiet();
        container.querySelector('#bmi-proteinResult').innerText = ((tdee * diet.protein / 100) / 4).toFixed(0) + 'g';
        container.querySelector('#bmi-carbsResult').innerText = ((tdee * diet.carbs / 100) / 4).toFixed(0) + 'g';
        container.querySelector('#bmi-fatsResult').innerText = ((tdee * diet.fats / 100) / 9).toFixed(0) + 'g';
    }

    function exportToCSV() {
        if (currentFilteredHistory.length === 0) {
            alert(t('noRecords', 'No records to export.'));
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Name,Weight,Height,BMI,Date\n";

        currentFilteredHistory.forEach(function(rowArray) {
            let row = [
                (rowArray.name + ' ' + (rowArray.surname || '')).trim().replace(/,/g, ''),
                rowArray.weight_kg,
                rowArray.height_cm,
                rowArray.bmi,
                (rowArray.created_at || '').replace(/,/g, '')
            ];
            csvContent += row.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        const userName = userSelect ? userSelect.value : 'all';
        const fileName = userName === 'all' ? "bmi_records_all.csv" : `bmi_records_${userName.replace(/ /g, '_')}.csv`;
        link.setAttribute("download", fileName);
        container.appendChild(link);
        link.click();
        container.removeChild(link);
    }

    async function calculateBMI() {
        const nameInput = container.querySelector('#bmi-name').value;
        const surnameInput = container.querySelector('#bmi-surname').value;
        const weightInput = parseFloat(container.querySelector('#bmi-mainWeight').value);
        const heightInput = parseFloat(container.querySelector('#bmi-mainHeight').value);
        const weightUnit = container.querySelector('#bmi-weightUnit').value;
        const heightUnit = container.querySelector('#bmi-heightUnit').value;
        const age = parseInt(container.querySelector('#bmi-age').value);
        const gender = container.querySelector('#bmi-gender').value;
        const activityMultiplier = parseFloat(container.querySelector('#bmi-activity').value);

        if (isNaN(weightInput) || weightInput <= 0 || isNaN(heightInput) || heightInput <= 0 || isNaN(age) || age <= 0) {
            alert(t('validationError', 'Please enter valid positive numbers for weight, height, and age.'));
            return;
        }

        let kilograms = weightUnit === 'lbs' ? weightInput * 0.453592 : weightInput;
        let centimeters = heightUnit === 'in' ? heightInput * 2.54 : heightInput;
        let meters = centimeters / 100;

        const bmiValue = kilograms / (meters * meters);

        let bmr;
        if (gender === 'male') {
            bmr = (10 * kilograms) + (6.25 * centimeters) - (5 * age) + 5;
        } else {
            bmr = (10 * kilograms) + (6.25 * centimeters) - (5 * age) - 161;
        }
        const tdee = bmr * activityMultiplier;

        container.querySelector('#bmi-bmiResult').innerText = bmiValue.toFixed(2);
        container.querySelector('#bmi-calInResult').innerText = bmr.toFixed(0);
        container.querySelector('#bmi-calOutResult').innerText = tdee.toFixed(0);

        // Ideal Weight
        const minIdealKg = 18.5 * (meters * meters);
        const maxIdealKg = 24.9 * (meters * meters);
        let idealRangeText = weightUnit === 'lbs' ?
            `${(minIdealKg * 2.20462).toFixed(1)} - ${(maxIdealKg * 2.20462).toFixed(1)} lbs` :
            `${minIdealKg.toFixed(1)} - ${maxIdealKg.toFixed(1)} kg`;
        container.querySelector('#bmi-idealWeightRange').innerText = idealRangeText;

        // Macros (diet preset driven)
        const diet = getCurrentDiet();
        container.querySelector('#bmi-proteinResult').innerText = ((tdee * diet.protein / 100) / 4).toFixed(0) + 'g';
        container.querySelector('#bmi-carbsResult').innerText = ((tdee * diet.carbs / 100) / 4).toFixed(0) + 'g';
        container.querySelector('#bmi-fatsResult').innerText = ((tdee * diet.fats / 100) / 9).toFixed(0) + 'g';
        updateMacroLabels();

        // Gauge
        let percent = ((bmiValue - 15) / (40 - 15)) * 100;
        container.querySelector('#bmi-gaugePointer').style.left = Math.max(0, Math.min(100, percent)) + '%';

        const commentEl = container.querySelector('#bmi-commentResult');
        let statusClass = "";
        let commentText = "";

        if (bmiValue < 18.5) { commentText = t('underweight', 'Underweight'); statusClass = "status-underweight"; }
        else if (bmiValue <= 24.9) { commentText = t('normal', 'Normal weight'); statusClass = "status-normal"; }
        else if (bmiValue < 30) { commentText = t('overweight', 'Overweight'); statusClass = "status-overweight"; }
        else { commentText = t('obese', 'Obese'); statusClass = "status-obese"; }

        commentEl.innerText = commentText;
        commentEl.className = "result-value " + statusClass;
        container.querySelector('#bmi-bmiResult').className = "result-value " + statusClass;

        resultsArea.classList.remove('hidden');
        resultsArea.focus(); // Move focus to results for screen readers

        // Save to Database via REST API
        const fullName = wpUser ? wpUser.name : (`${nameInput} ${surnameInput}`.trim() || 'Anonymous');

        try {
            // Enhancement 4: Use user-specific endpoint if logged in
            const endpoint = wpUser ? 'user-record' : 'record';
            const bodyData = wpUser ? {
                weight_kg: kilograms.toFixed(2),
                height_cm: centimeters.toFixed(2),
                tdee: tdee.toFixed(0),
                diet_type: dietTypeSelect ? dietTypeSelect.value : config.dietType
            } : {
                name: nameInput || 'Anonymous',
                surname: surnameInput,
                weight_kg: kilograms.toFixed(2),
                height_cm: centimeters.toFixed(2)
            };

            const response = await fetch(bmiCalcData.restUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': bmiCalcData.nonce,
                },
                body: JSON.stringify(bodyData)
            });
            const resData = await response.json();
            if (resData.success) {
                if (wpUser && config.showHistory) {
                    await loadUserHistory();
                } else if (config.showHistory) {
                    await loadHistory();
                    if (userSelect) {
                        userSelect.value = fullName;
                        loadHistory();
                    }
                }
            }
        } catch (err) {
            console.error("Failed to save record:", err);
        }

        // Enhancement 2: Show lead capture form after calculation
        const leadForm = container.querySelector('#bmi-leadForm');
        if (leadForm) {
            leadForm.classList.remove('hidden');
            // Store current BMI for lead submission
            leadForm.dataset.currentBmi = bmiValue.toFixed(2);
        }
    }

    async function loadHistory() {
        if (!historyArea || !historyBody || !userSelect) return;

        try {
            const response = await fetch(bmiCalcData.restUrl + 'history');
            const history = await response.json();

            const uniqueUsers = [...new Set(history.map(item => `${item.name} ${item.surname}`.trim()))];
            const currentSelection = userSelect.value;
            userSelect.innerHTML = '<option value="all">All Users</option>';
            uniqueUsers.forEach(user => {
                let opt = document.createElement('option');
                opt.value = user; opt.innerText = user;
                if (user === currentSelection) opt.selected = true;
                userSelect.appendChild(opt);
            });

            if (history.length === 0) { historyArea.classList.add('hidden'); return; }
            historyArea.classList.remove('hidden');
            historyBody.innerHTML = '';

            let filteredHistory = history;
            if (userSelect.value !== 'all') {
                filteredHistory = history.filter(record => `${record.name} ${record.surname}`.trim() === userSelect.value);
            }
            currentFilteredHistory = filteredHistory;

            filteredHistory.forEach(record => {
                let row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.name} ${record.surname}</td>
                    <td>${record.weight_kg} kg</td>
                    <td>${record.height_cm} cm</td>
                    <td><strong>${record.bmi}</strong></td>
                    <td>${record.created_at}</td>
                `;
                historyBody.appendChild(row);
            });
            updateChart(filteredHistory, userSelect.value);
        } catch (err) {
            console.error("Failed to load history:", err);
        }
    }

    /**
     * Enhancement 4: Load personal history from user meta
     */
    async function loadUserHistory() {
        if (!historyArea || !historyBody) return;

        try {
            const response = await fetch(bmiCalcData.restUrl + 'user-history', {
                headers: { 'X-WP-Nonce': bmiCalcData.nonce }
            });
            const history = await response.json();

            if (history.length === 0) { historyArea.classList.add('hidden'); return; }
            historyArea.classList.remove('hidden');
            historyBody.innerHTML = '';

            // Hide user filter for personal history
            if (userSelect) {
                userSelect.closest('.user-filter').style.display = 'none';
            }

            currentFilteredHistory = history.map(r => ({
                name: wpUser.name,
                surname: '',
                weight_kg: r.weight_kg,
                height_cm: r.height_cm,
                bmi: r.bmi,
                created_at: r.created_at
            }));

            currentFilteredHistory.forEach(record => {
                let row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.name}</td>
                    <td>${record.weight_kg} kg</td>
                    <td>${record.height_cm} cm</td>
                    <td><strong>${record.bmi}</strong></td>
                    <td>${record.created_at}</td>
                `;
                historyBody.appendChild(row);
            });

            updateChart(currentFilteredHistory, wpUser.name);
        } catch (err) {
            console.error("Failed to load user history:", err);
        }
    }

    function updateChart(data, user) {
        if (!chartContainer) return;
        if (data.length === 0 || user === 'all') {
            chartContainer.classList.add('hidden');
            if (bmiChartInstance) bmiChartInstance.destroy();
            return;
        }
        chartContainer.classList.remove('hidden');
        const chartData = [...data].reverse();
        const labels = chartData.map(d => (d.created_at || '').split(' ')[0]);
        const bmiValues = chartData.map(d => parseFloat(d.bmi));
        const ctx = container.querySelector('#bmi-bmiChart').getContext('2d');
        if (bmiChartInstance) bmiChartInstance.destroy();
        bmiChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: `BMI Progress for ${user}`,
                    data: bmiValues,
                    borderColor: config.primaryColor,
                    backgroundColor: config.primaryColor + '1a',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function clearForm() {
        if (!wpUser) {
            container.querySelector('#bmi-name').value = "";
            container.querySelector('#bmi-surname').value = "";
        }
        container.querySelector('#bmi-mainWeight').value = "";
        container.querySelector('#bmi-mainHeight').value = "";
        container.querySelector('#bmi-age').value = "";
        container.querySelector('#bmi-gender').value = "male";
        container.querySelector('#bmi-activity').value = "1.2";
        if (dietTypeSelect) {
            dietTypeSelect.value = "balanced";
            const diet = DIET_PRESETS['balanced'];
            config.macroProtein = diet.protein;
            config.macroCarbs = diet.carbs;
            config.macroFats = diet.fats;
            updateMacroLabels();
        }
        resultsArea.classList.add('hidden');
        container.querySelector('#bmi-gaugePointer').style.left = '0%';
        // Hide lead form on clear
        const leadForm = container.querySelector('#bmi-leadForm');
        if (leadForm) leadForm.classList.add('hidden');
    }

    // --- Enhancement 2: Lead capture form submission ---
    const leadSubmitBtn = container.querySelector('#bmi-leadSubmit');
    if (leadSubmitBtn) {
        leadSubmitBtn.addEventListener('click', async function() {
            const emailInput = container.querySelector('#bmi-leadEmail');
            const optInCheck = container.querySelector('#bmi-leadOptIn');
            const messageDiv = container.querySelector('#bmi-leadMessage');
            const leadForm = container.querySelector('#bmi-leadForm');

            if (!optInCheck.checked) {
                messageDiv.textContent = t('pleaseAgree', 'Please agree to receive emails first.');
                messageDiv.className = 'bmi-lead-success error';
                messageDiv.classList.remove('hidden');
                return;
            }

            const email = emailInput.value.trim();
            if (!email || !email.includes('@')) {
                messageDiv.textContent = t('validEmail', 'Please enter a valid email address.');
                messageDiv.className = 'bmi-lead-success error';
                messageDiv.classList.remove('hidden');
                return;
            }

            try {
                const response = await fetch(bmiCalcData.restUrl + 'lead', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': bmiCalcData.nonce,
                    },
                    body: JSON.stringify({
                        email: email,
                        name: wpUser ? wpUser.name : (container.querySelector('#bmi-name').value || ''),
                        bmi: parseFloat(leadForm.dataset.currentBmi) || 0,
                    })
                });
                const data = await response.json();
                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.className = 'bmi-lead-success success';
                    emailInput.value = '';
                    optInCheck.checked = false;
                } else {
                    messageDiv.textContent = data.error || 'Something went wrong.';
                    messageDiv.className = 'bmi-lead-success error';
                }
                messageDiv.classList.remove('hidden');
            } catch (err) {
                messageDiv.textContent = t('networkError', 'Network error. Please try again.');
                messageDiv.className = 'bmi-lead-success error';
                messageDiv.classList.remove('hidden');
            }
        });
    }

    // --- Unit Converters ---
    // Only init if converter section exists (showConverter=true)
    const kgIn = container.querySelector('#bmi-kgInput');
    if (kgIn) {
        const lbsIn = container.querySelector('#bmi-lbsInput');
        const gmIn = container.querySelector('#bmi-gmInput');
        const ozIn = container.querySelector('#bmi-ozInput');

        kgIn.addEventListener('input', () => {
            const v = parseFloat(kgIn.value);
            if (isNaN(v)) { lbsIn.value = gmIn.value = ozIn.value = ''; return; }
            lbsIn.value = (v * 2.20462).toFixed(4);
            gmIn.value = (v * 1000).toFixed(4);
            ozIn.value = (v * 35.274).toFixed(4);
        });
        lbsIn.addEventListener('input', () => {
            const v = parseFloat(lbsIn.value);
            if (isNaN(v)) { kgIn.value = gmIn.value = ozIn.value = ''; return; }
            kgIn.value = (v / 2.20462).toFixed(4);
            gmIn.value = (v * 453.592).toFixed(4);
            ozIn.value = (v * 16).toFixed(4);
        });
        gmIn.addEventListener('input', () => {
            const v = parseFloat(gmIn.value);
            if (isNaN(v)) { kgIn.value = lbsIn.value = ozIn.value = ''; return; }
            kgIn.value = (v / 1000).toFixed(4);
            lbsIn.value = (v / 453.592).toFixed(4);
            ozIn.value = (v / 28.3495).toFixed(4);
        });
        ozIn.addEventListener('input', () => {
            const v = parseFloat(ozIn.value);
            if (isNaN(v)) { kgIn.value = lbsIn.value = gmIn.value = ''; return; }
            kgIn.value = (v / 35.274).toFixed(4);
            lbsIn.value = (v / 16).toFixed(4);
            gmIn.value = (v * 28.3495).toFixed(4);
        });
    }

    // Height
    const cmIn = container.querySelector('#bmi-cmInput');
    if (cmIn) {
        const mIn = container.querySelector('#bmi-mInput');
        const inIn = container.querySelector('#bmi-inchInput');
        const ftIn = container.querySelector('#bmi-ftInput');

        cmIn.addEventListener('input', () => {
            const v = parseFloat(cmIn.value);
            if (isNaN(v)) { mIn.value = inIn.value = ftIn.value = ''; return; }
            mIn.value = (v / 100).toFixed(4);
            inIn.value = (v / 2.54).toFixed(4);
            ftIn.value = (v / 30.48).toFixed(4);
        });
        mIn.addEventListener('input', () => {
            const v = parseFloat(mIn.value);
            if (isNaN(v)) { cmIn.value = inIn.value = ftIn.value = ''; return; }
            cmIn.value = (v * 100).toFixed(4);
            inIn.value = (v * 39.3701).toFixed(4);
            ftIn.value = (v * 3.28084).toFixed(4);
        });
        inIn.addEventListener('input', () => {
            const v = parseFloat(inIn.value);
            if (isNaN(v)) { cmIn.value = mIn.value = ftIn.value = ''; return; }
            cmIn.value = (v * 2.54).toFixed(4);
            mIn.value = (v / 39.3701).toFixed(4);
            ftIn.value = (v / 12).toFixed(4);
        });
        ftIn.addEventListener('input', () => {
            const v = parseFloat(ftIn.value);
            if (isNaN(v)) { cmIn.value = mIn.value = inIn.value = ''; return; }
            cmIn.value = (v * 30.48).toFixed(4);
            mIn.value = (v / 3.28084).toFixed(4);
            inIn.value = (v * 12).toFixed(4);
        });
    }
});
