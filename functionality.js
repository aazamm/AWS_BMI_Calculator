document.addEventListener('DOMContentLoaded', function () {

    // --- Diet Presets ---
    const DIET_PRESETS = {
        'balanced':     { protein: 30, carbs: 40, fats: 30 },
        'keto':         { protein: 20, carbs: 5,  fats: 75 },
        'paleo':        { protein: 30, carbs: 20, fats: 50 },
        'high-protein': { protein: 40, carbs: 30, fats: 30 },
    };

    // --- i18n ---
    let currentLang = localStorage.getItem('bmi-lang') || 'en';

    function t(key) {
        if (typeof BMI_TRANSLATIONS !== 'undefined' && BMI_TRANSLATIONS[currentLang] && BMI_TRANSLATIONS[currentLang][key]) {
            return BMI_TRANSLATIONS[currentLang][key];
        }
        if (typeof BMI_TRANSLATIONS !== 'undefined' && BMI_TRANSLATIONS['en'] && BMI_TRANSLATIONS['en'][key]) {
            return BMI_TRANSLATIONS['en'][key];
        }
        return key;
    }

    function applyTranslations() {
        // Title
        const titleEl = document.getElementById('calcTitle');
        if (titleEl) titleEl.innerText = t('title');

        // Labels
        const labelMap = {
            'labelName': 'name', 'labelSurname': 'surname', 'labelAge': 'age',
            'labelGender': 'gender', 'labelActivity': 'activityLevel',
            'labelDietType': 'dietType',
        };
        Object.entries(labelMap).forEach(([id, key]) => {
            const el = document.getElementById(id);
            if (el) el.innerText = t(key);
        });

        // Placeholders
        const nameEl = document.getElementById('name');
        const surnameEl = document.getElementById('surname');
        if (nameEl && nameEl.type !== 'hidden') nameEl.placeholder = t('enterName');
        if (surnameEl && surnameEl.type !== 'hidden') surnameEl.placeholder = t('enterSurname');

        // Gender options
        const genderSel = document.getElementById('gender');
        if (genderSel) {
            genderSel.options[0].text = t('male');
            genderSel.options[1].text = t('female');
        }

        // Activity options
        const actSel = document.getElementById('activity');
        if (actSel) {
            const actKeys = ['sedentary', 'lightlyActive', 'moderatelyActive', 'veryActive', 'extraActive'];
            actKeys.forEach((key, i) => { if (actSel.options[i]) actSel.options[i].text = t(key); });
        }

        // Diet type options
        const dietSel = document.getElementById('dietType');
        if (dietSel) {
            const dietKeys = ['balanced', 'keto', 'paleo', 'highProtein'];
            dietKeys.forEach((key, i) => { if (dietSel.options[i]) dietSel.options[i].text = t(key); });
        }

        // Buttons
        const calcBtn = document.getElementById('calcBtn');
        const clearBtn = document.getElementById('clearBtn');
        if (calcBtn) calcBtn.innerText = t('calculate');
        if (clearBtn) clearBtn.innerText = t('clear');

        // Theme button
        const isDark = document.body.classList.contains('dark-mode');
        themeBtn.innerText = isDark ? t('lightMode') : t('darkMode');

        // Update macro labels with current diet percentages
        updateMacroLabels();

        // Result card headings (if visible)
        document.querySelectorAll('#resultsArea .result-card h4').forEach(h4 => {
            const text = h4.getAttribute('data-i18n-key');
            if (text) h4.innerText = t(text);
        });
    }

    // --- Accessibility: Contrast Helper ---
    function getContrastYIQ(hexcolor) {
        hexcolor = hexcolor.replace("#", "");
        if (hexcolor.length === 3) {
            hexcolor = hexcolor.split('').map(function(c) { return c + c; }).join('');
        }
        var r = parseInt(hexcolor.substr(0, 2), 16);
        var g = parseInt(hexcolor.substr(2, 2), 16);
        var b = parseInt(hexcolor.substr(4, 2), 16);
        var yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? 'black' : 'white';
    }

    // Apply contrast text color to accent buttons
    var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim();
    if (accentColor) {
        document.documentElement.style.setProperty('--accent-text', getContrastYIQ(accentColor));
    }

    // --- Theme Toggle ---
    const themeBtn = document.getElementById('themeBtn');
    const body = document.body;

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeBtn.innerText = t('lightMode');
    }

    themeBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        themeBtn.innerText = isDark ? t('lightMode') : t('darkMode');
    });

    // --- Language Selector ---
    const langSelect = document.getElementById('langSelect');
    if (langSelect) {
        langSelect.value = currentLang;
        langSelect.addEventListener('change', () => {
            currentLang = langSelect.value;
            localStorage.setItem('bmi-lang', currentLang);
            applyTranslations();
        });
    }

    // --- Unit Preference Persistence ---
    const weightUnitSelect = document.getElementById('weightUnit');
    const heightUnitSelect = document.getElementById('heightUnit');

    function restoreUnitPreferences() {
        if (window.BMI_USER && window.BMI_USER.weight_unit) {
            weightUnitSelect.value = window.BMI_USER.weight_unit;
            heightUnitSelect.value = window.BMI_USER.height_unit;
        } else if (!window.BMI_USER) {
            const savedWeight = localStorage.getItem('bmi-weight-unit');
            const savedHeight = localStorage.getItem('bmi-height-unit');
            if (savedWeight) weightUnitSelect.value = savedWeight;
            if (savedHeight) heightUnitSelect.value = savedHeight;
        }
    }

    function saveUnitPreference() {
        const wu = weightUnitSelect.value;
        const hu = heightUnitSelect.value;
        if (window.BMI_USER) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_preferences', weight_unit: wu, height_unit: hu })
            }).catch(err => console.error('Failed to save preferences:', err));
        } else {
            localStorage.setItem('bmi-weight-unit', wu);
            localStorage.setItem('bmi-height-unit', hu);
        }
    }

    weightUnitSelect.addEventListener('change', saveUnitPreference);
    heightUnitSelect.addEventListener('change', saveUnitPreference);
    restoreUnitPreferences();

    // --- Main Calculator Logic ---
    const calcBtn = document.getElementById('calcBtn');
    const clearBtn = document.getElementById('clearBtn');
    const resultsArea = document.getElementById('resultsArea');
    const historyArea = document.getElementById('historyArea');
    const historyBody = document.getElementById('historyBody');
    const userSelect = document.getElementById('userSelect');
    const chartContainer = document.getElementById('chartContainer');
    const exportBtn = document.getElementById('exportBtn');
    const reportBtnArea = document.getElementById('reportBtnArea');
    const downloadReportBtn = document.getElementById('downloadReportBtn');
    let bmiChartInstance = null;
    let currentFilteredHistory = [];
    let lastCalcData = {};

    userSelect.addEventListener('change', loadHistory);
    exportBtn.addEventListener('click', exportToCSV);
    if (downloadReportBtn) downloadReportBtn.addEventListener('click', generatePDFReport);

    // Load goal weight if logged in
    if (window.BMI_USER) {
        loadGoalWeight();
    }

    loadHistory();

    calcBtn.addEventListener('click', calculateBMI);
    clearBtn.addEventListener('click', clearForm);

    // Show/hide hip field based on gender
    const genderSelect = document.getElementById('gender');
    const hipGroup = document.getElementById('hipGroup');
    function updateHipVisibility() {
        if (hipGroup) {
            hipGroup.style.display = genderSelect.value === 'female' ? 'block' : 'block';
            const hipNote = document.getElementById('hipNote');
            if (hipNote) hipNote.style.display = genderSelect.value === 'female' ? 'inline' : 'none';
        }
    }
    genderSelect.addEventListener('change', updateHipVisibility);
    updateHipVisibility();

    // --- Diet Type Change ---
    const dietTypeSelect = document.getElementById('dietType');
    function getCurrentDiet() {
        return DIET_PRESETS[dietTypeSelect.value] || DIET_PRESETS['balanced'];
    }

    function updateMacroLabels() {
        const diet = getCurrentDiet();
        const proteinLabel = document.getElementById('proteinLabel');
        const carbsLabel = document.getElementById('carbsLabel');
        const fatsLabel = document.getElementById('fatsLabel');
        if (proteinLabel) proteinLabel.innerText = t('protein') + ' (' + diet.protein + '%)';
        if (carbsLabel) carbsLabel.innerText = t('carbs') + ' (' + diet.carbs + '%)';
        if (fatsLabel) fatsLabel.innerText = t('fats') + ' (' + diet.fats + '%)';
    }

    dietTypeSelect.addEventListener('change', () => {
        updateMacroLabels();
        // Recalculate if results are visible
        if (!resultsArea.classList.contains('hidden')) {
            recalcMacros();
        }
    });

    function recalcMacros() {
        const tdeeText = document.getElementById('calOutResult').innerText;
        const tdee = parseFloat(tdeeText);
        if (isNaN(tdee) || tdee <= 0) return;
        const diet = getCurrentDiet();
        document.getElementById('proteinResult').innerText = ((tdee * diet.protein / 100) / 4).toFixed(0) + 'g';
        document.getElementById('carbsResult').innerText = ((tdee * diet.carbs / 100) / 4).toFixed(0) + 'g';
        document.getElementById('fatsResult').innerText = ((tdee * diet.fats / 100) / 9).toFixed(0) + 'g';
    }

    // Apply initial translations
    applyTranslations();

    function exportToCSV() {
        if (currentFilteredHistory.length === 0) {
            alert(t('noRecords'));
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Name,Weight,Height,BMI,Body Fat %,WHtR,Date\n";

        currentFilteredHistory.forEach(function(rowArray) {
            let row = [
                (rowArray.name + ' ' + (rowArray.surname || '')).trim().replace(/,/g, ''),
                rowArray.weight_kg,
                rowArray.height_cm,
                rowArray.bmi,
                rowArray.body_fat_pct || '',
                rowArray.whtr || '',
                rowArray.created_at.replace(/,/g, '')
            ];
            csvContent += row.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        const fileName = userSelect.value === 'all' ? "bmi_records_all.csv" : `bmi_records_${userSelect.value.replace(/ /g, '_')}.csv`;
        link.setAttribute("download", fileName);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    async function loadGoalWeight() {
        try {
            const response = await fetch('index.php?action=get_goal');
            const data = await response.json();
            if (data.goal_weight_kg) {
                const goalInput = document.getElementById('goalWeight');
                if (goalInput) goalInput.value = data.goal_weight_kg;
            }
        } catch (err) {
            console.error("Failed to load goal:", err);
        }
    }

    async function calculateBMI() {
        const nameInput = document.getElementById('name').value;
        const surnameInput = document.getElementById('surname').value;
        const weightInput = parseFloat(document.getElementById('mainWeight').value);
        const heightInput = parseFloat(document.getElementById('mainHeight').value);
        const weightUnit = document.getElementById('weightUnit').value;
        const heightUnit = document.getElementById('heightUnit').value;
        const age = parseInt(document.getElementById('age').value);
        const gender = document.getElementById('gender').value;
        const activityMultiplier = parseFloat(document.getElementById('activity').value);

        if (isNaN(weightInput) || weightInput <= 0 || isNaN(heightInput) || heightInput <= 0 || isNaN(age) || age <= 0) {
            alert(t('validationError'));
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

        document.getElementById('bmiResult').innerText = bmiValue.toFixed(2);
        document.getElementById('calInResult').innerText = bmr.toFixed(0);
        document.getElementById('calOutResult').innerText = tdee.toFixed(0);

        // Ideal Weight
        const minIdealKg = 18.5 * (meters * meters);
        const maxIdealKg = 24.9 * (meters * meters);
        let idealRangeText = weightUnit === 'lbs' ?
            `${(minIdealKg * 2.20462).toFixed(1)} - ${(maxIdealKg * 2.20462).toFixed(1)} lbs` :
            `${minIdealKg.toFixed(1)} - ${maxIdealKg.toFixed(1)} kg`;
        document.getElementById('idealWeightRange').innerText = idealRangeText;

        // Macros (diet preset driven)
        const diet = getCurrentDiet();
        document.getElementById('proteinResult').innerText = ((tdee * diet.protein / 100) / 4).toFixed(0) + 'g';
        document.getElementById('carbsResult').innerText = ((tdee * diet.carbs / 100) / 4).toFixed(0) + 'g';
        document.getElementById('fatsResult').innerText = ((tdee * diet.fats / 100) / 9).toFixed(0) + 'g';
        updateMacroLabels();

        // Gauge
        let percent = ((bmiValue - 15) / (40 - 15)) * 100;
        document.getElementById('gaugePointer').style.left = Math.max(0, Math.min(100, percent)) + '%';

        const commentEl = document.getElementById('commentResult');
        let statusClass = "";
        let commentText = "";

        if (bmiValue < 18.5) { commentText = t('underweight'); statusClass = "status-underweight"; }
        else if (bmiValue <= 24.9) { commentText = t('normal'); statusClass = "status-normal"; }
        else if (bmiValue < 30) { commentText = t('overweight'); statusClass = "status-overweight"; }
        else { commentText = t('obese'); statusClass = "status-obese"; }

        commentEl.innerText = commentText;
        commentEl.className = "result-value " + statusClass;
        document.getElementById('bmiResult').className = "result-value " + statusClass;

        // --- Body Fat % (Navy Method) ---
        const neckCm = parseFloat(document.getElementById('neckCirc')?.value);
        const waistCm = parseFloat(document.getElementById('waistCirc')?.value);
        const hipCm = parseFloat(document.getElementById('hipCirc')?.value);
        let bodyFatPct = null;
        let whtr = null;

        const bodyFatCard = document.getElementById('bodyFatCard');
        const whtrCard = document.getElementById('whtrCard');

        if (!isNaN(waistCm) && waistCm > 0) {
            whtr = waistCm / centimeters;
            document.getElementById('whtrResult').innerText = whtr.toFixed(3);
            const whtrCatEl = document.getElementById('whtrCategory');
            if (whtr < 0.4) { whtrCatEl.innerText = "Underweight risk"; whtrCatEl.className = "result-subtext status-underweight"; }
            else if (whtr < 0.5) { whtrCatEl.innerText = "Healthy"; whtrCatEl.className = "result-subtext status-normal"; }
            else if (whtr < 0.6) { whtrCatEl.innerText = "Overweight risk"; whtrCatEl.className = "result-subtext status-overweight"; }
            else { whtrCatEl.innerText = "Obese risk"; whtrCatEl.className = "result-subtext status-obese"; }
            whtrCard.classList.remove('hidden');
        } else {
            whtrCard.classList.add('hidden');
        }

        if (!isNaN(neckCm) && !isNaN(waistCm) && neckCm > 0 && waistCm > 0 && waistCm > neckCm) {
            if (gender === 'male') {
                bodyFatPct = 495 / (1.0324 - 0.19077 * Math.log10(waistCm - neckCm) + 0.15456 * Math.log10(centimeters)) - 450;
            } else if (!isNaN(hipCm) && hipCm > 0) {
                bodyFatPct = 495 / (1.29579 - 0.35004 * Math.log10(waistCm + hipCm - neckCm) + 0.22100 * Math.log10(centimeters)) - 450;
            }
            if (bodyFatPct !== null && bodyFatPct >= 2 && bodyFatPct <= 60) {
                document.getElementById('bodyFatResult').innerText = bodyFatPct.toFixed(1) + '%';
                const bfCatEl = document.getElementById('bodyFatCategory');
                if (gender === 'male') {
                    if (bodyFatPct < 6) { bfCatEl.innerText = "Essential fat"; bfCatEl.className = "result-subtext status-underweight"; }
                    else if (bodyFatPct < 14) { bfCatEl.innerText = "Athletic"; bfCatEl.className = "result-subtext status-normal"; }
                    else if (bodyFatPct < 18) { bfCatEl.innerText = "Fit"; bfCatEl.className = "result-subtext status-normal"; }
                    else if (bodyFatPct < 25) { bfCatEl.innerText = "Average"; bfCatEl.className = "result-subtext status-overweight"; }
                    else { bfCatEl.innerText = "Above average"; bfCatEl.className = "result-subtext status-obese"; }
                } else {
                    if (bodyFatPct < 14) { bfCatEl.innerText = "Essential fat"; bfCatEl.className = "result-subtext status-underweight"; }
                    else if (bodyFatPct < 21) { bfCatEl.innerText = "Athletic"; bfCatEl.className = "result-subtext status-normal"; }
                    else if (bodyFatPct < 25) { bfCatEl.innerText = "Fit"; bfCatEl.className = "result-subtext status-normal"; }
                    else if (bodyFatPct < 32) { bfCatEl.innerText = "Average"; bfCatEl.className = "result-subtext status-overweight"; }
                    else { bfCatEl.innerText = "Above average"; bfCatEl.className = "result-subtext status-obese"; }
                }
                bodyFatCard.classList.remove('hidden');
            } else {
                bodyFatPct = null;
                bodyFatCard.classList.add('hidden');
            }
        } else {
            bodyFatCard.classList.add('hidden');
        }

        resultsArea.classList.remove('hidden');
        resultsArea.focus(); // Move focus to results for screen readers
        if (reportBtnArea) reportBtnArea.classList.remove('hidden');

        // Store last calculation for PDF report
        lastCalcData = {
            bmi: bmiValue.toFixed(2), category: commentText,
            bmr: bmr.toFixed(0), tdee: tdee.toFixed(0),
            weight: kilograms.toFixed(1), height: centimeters.toFixed(1),
            idealRange: idealRangeText, gender: gender,
            bodyFatPct: bodyFatPct ? bodyFatPct.toFixed(1) : null,
            whtr: whtr ? whtr.toFixed(3) : null,
            protein: ((tdee * diet.protein / 100) / 4).toFixed(0) + 'g',
            carbs: ((tdee * diet.carbs / 100) / 4).toFixed(0) + 'g',
            fats: ((tdee * diet.fats / 100) / 9).toFixed(0) + 'g',
        };

        // Save goal weight if set
        const goalInput = document.getElementById('goalWeight');
        if (goalInput && goalInput.value && window.BMI_USER) {
            const goalWeightUnit = document.getElementById('goalWeightUnit').value;
            let goalKg = parseFloat(goalInput.value);
            if (goalWeightUnit === 'lbs') goalKg = goalKg * 0.453592;
            if (goalKg > 0) {
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'set_goal', goal_weight_kg: goalKg.toFixed(2) })
                }).catch(err => console.error("Failed to save goal:", err));
            }
        }

        // Save to Database
        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save',
                    name: nameInput || 'Anonymous',
                    surname: surnameInput || '',
                    weight_kg: kilograms.toFixed(2),
                    height_cm: centimeters.toFixed(2),
                    gender: gender,
                    neck_cm: isNaN(neckCm) ? null : neckCm,
                    waist_cm: isNaN(waistCm) ? null : waistCm,
                    hip_cm: isNaN(hipCm) ? null : hipCm,
                    body_fat_pct: bodyFatPct,
                    whtr: whtr,
                    tdee: tdee.toFixed(0),
                    diet_type: dietTypeSelect.value
                })
            });
            const resData = await response.json();
            if (resData.success) {
                const fullName = `${nameInput} ${surnameInput}`.trim() || 'Anonymous';
                await loadHistory();
                if (!window.BMI_USER) {
                    userSelect.value = fullName;
                    loadHistory();
                }
            }
        } catch (err) {
            console.error("Failed to save record:", err);
        }
    }

    async function loadHistory() {
        try {
            const response = await fetch('index.php?action=get_history');
            const history = await response.json();

            const uniqueUsers = [...new Set(history.map(item => `${item.name} ${item.surname || ''}`.trim()))];
            const currentSelection = userSelect.value;

            if (window.BMI_USER) {
                // Logged-in users only see their own records, no need for user filter
                userSelect.innerHTML = `<option value="${window.BMI_USER.name}">${window.BMI_USER.name}</option>`;
                userSelect.value = window.BMI_USER.name;
            } else {
                userSelect.innerHTML = '<option value="all">All Users</option>';
                uniqueUsers.forEach(user => {
                    let opt = document.createElement('option');
                    opt.value = user; opt.innerText = user;
                    if (user === currentSelection) opt.selected = true;
                    userSelect.appendChild(opt);
                });
            }

            if (history.length === 0) { historyArea.classList.add('hidden'); return; }
            historyArea.classList.remove('hidden');
            historyBody.innerHTML = '';

            let filteredHistory = history;
            if (!window.BMI_USER && userSelect.value !== 'all') {
                filteredHistory = history.filter(record => `${record.name} ${record.surname || ''}`.trim() === userSelect.value);
            }
            currentFilteredHistory = filteredHistory;

            filteredHistory.forEach(record => {
                let row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.name} ${record.surname || ''}</td>
                    <td>${record.weight_kg} kg</td>
                    <td>${record.height_cm} cm</td>
                    <td><strong>${record.bmi}</strong></td>
                    <td>${record.created_at.split('.')[0]}</td>
                `;
                historyBody.appendChild(row);
            });

            // Calculate velocity and projection
            updateVelocityAndProjection(filteredHistory);

            updateChart(filteredHistory, userSelect.value);
        } catch (err) {
            console.error("Failed to load history:", err);
        }
    }

    function updateVelocityAndProjection(records) {
        const velocityCard = document.getElementById('velocityCard');
        const projectionCard = document.getElementById('projectionCard');
        if (!velocityCard) return;

        if (records.length < 2) {
            velocityCard.classList.add('hidden');
            projectionCard.classList.add('hidden');
            return;
        }

        // Records are newest-first, take up to last 4
        const recent = records.slice(0, Math.min(4, records.length));
        const newest = recent[0];
        const oldest = recent[recent.length - 1];
        const daysDiff = (new Date(newest.created_at) - new Date(oldest.created_at)) / (1000 * 60 * 60 * 24);

        if (daysDiff < 1) {
            velocityCard.classList.add('hidden');
            projectionCard.classList.add('hidden');
            return;
        }

        const weightDiff = parseFloat(newest.weight_kg) - parseFloat(oldest.weight_kg);
        const velocityPerWeek = (weightDiff / daysDiff) * 7;

        document.getElementById('velocityResult').innerText = (velocityPerWeek >= 0 ? '+' : '') + velocityPerWeek.toFixed(2) + ' kg/wk';
        const velEl = document.getElementById('velocityResult');
        velEl.className = 'result-value ' + (velocityPerWeek > 0 ? 'status-overweight' : velocityPerWeek < 0 ? 'status-normal' : '');
        velocityCard.classList.remove('hidden');

        // Projection
        const goalInput = document.getElementById('goalWeight');
        if (goalInput && goalInput.value && velocityPerWeek !== 0) {
            const goalWeightUnit = document.getElementById('goalWeightUnit')?.value || 'kg';
            let goalKg = parseFloat(goalInput.value);
            if (goalWeightUnit === 'lbs') goalKg = goalKg * 0.453592;
            const currentKg = parseFloat(newest.weight_kg);
            const diff = goalKg - currentKg;

            if ((diff < 0 && velocityPerWeek < 0) || (diff > 0 && velocityPerWeek > 0)) {
                const weeksToGoal = Math.abs(diff / velocityPerWeek);
                const targetDate = new Date();
                targetDate.setDate(targetDate.getDate() + weeksToGoal * 7);
                document.getElementById('projectionResult').innerText = targetDate.toLocaleDateString();
                document.getElementById('projectionSubtext').innerText = `~${Math.round(weeksToGoal)} weeks at current pace`;
                projectionCard.classList.remove('hidden');
            } else if (Math.abs(diff) < 0.5) {
                document.getElementById('projectionResult').innerText = 'Goal reached!';
                document.getElementById('projectionSubtext').innerText = '';
                projectionCard.classList.remove('hidden');
            } else {
                document.getElementById('projectionResult').innerText = 'N/A';
                document.getElementById('projectionSubtext').innerText = 'Trend moving away from goal';
                projectionCard.classList.remove('hidden');
            }
        } else {
            projectionCard.classList.add('hidden');
        }
    }

    function updateChart(data, user) {
        if (data.length === 0 || user === 'all') {
            chartContainer.classList.add('hidden');
            if (bmiChartInstance) bmiChartInstance.destroy();
            return;
        }
        chartContainer.classList.remove('hidden');
        const chartData = [...data].reverse();
        const labels = chartData.map(d => d.created_at.split(' ')[0]);
        const bmiValues = chartData.map(d => parseFloat(d.bmi));
        const weightValues = chartData.map(d => parseFloat(d.weight_kg));
        const bodyFatValues = chartData.map(d => d.body_fat_pct ? parseFloat(d.body_fat_pct) : null);
        const whtrValues = chartData.map(d => d.whtr ? parseFloat(d.whtr) : null);
        const hasBodyFat = bodyFatValues.some(v => v !== null);
        const hasWhtr = whtrValues.some(v => v !== null);

        const datasets = [
            {
                label: 'BMI',
                data: bmiValues,
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                yAxisID: 'y'
            },
            {
                label: 'Weight (kg)',
                data: weightValues,
                borderColor: '#e67e22',
                borderWidth: 1.5,
                fill: false,
                tension: 0.3,
                pointRadius: 3,
                yAxisID: 'y1'
            }
        ];

        // Body Fat % trend (shares BMI y-axis, similar numeric range)
        if (hasBodyFat) {
            datasets.push({
                label: 'Body Fat %',
                data: bodyFatValues,
                borderColor: '#9b59b6',
                borderWidth: 1.5,
                fill: false,
                tension: 0.3,
                pointRadius: 3,
                spanGaps: true,
                yAxisID: 'y'
            });
        }

        // WHtR trend (separate y2 axis, values 0.3-0.7)
        if (hasWhtr) {
            datasets.push({
                label: 'WHtR',
                data: whtrValues,
                borderColor: '#1abc9c',
                borderWidth: 1.5,
                borderDash: [3, 3],
                fill: false,
                tension: 0.3,
                pointRadius: 3,
                spanGaps: true,
                yAxisID: 'y2'
            });
        }

        // Goal BMI line
        const goalInput = document.getElementById('goalWeight');
        if (goalInput && goalInput.value && chartData.length > 0) {
            const goalWeightUnit = document.getElementById('goalWeightUnit')?.value || 'kg';
            let goalKg = parseFloat(goalInput.value);
            if (goalWeightUnit === 'lbs') goalKg = goalKg * 0.453592;
            const heightM = parseFloat(chartData[0].height_cm) / 100;
            if (heightM > 0 && goalKg > 0) {
                const goalBMI = goalKg / (heightM * heightM);
                datasets.push({
                    label: 'Goal BMI',
                    data: Array(labels.length).fill(goalBMI.toFixed(1)),
                    borderColor: '#27ae60',
                    borderDash: [5, 5],
                    borderWidth: 2,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y'
                });
            }
        }

        const ctx = document.getElementById('bmiChart').getContext('2d');
        if (bmiChartInstance) bmiChartInstance.destroy();
        bmiChartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'BMI' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Weight (kg)' },
                        grid: { drawOnChartArea: false }
                    },
                    y2: {
                        type: 'linear',
                        display: hasWhtr,
                        position: 'right',
                        title: { display: hasWhtr, text: 'WHtR' },
                        grid: { drawOnChartArea: false },
                        min: 0.3,
                        max: 0.7
                    }
                }
            }
        });
    }

    function clearForm() {
        if (!window.BMI_USER) {
            document.getElementById('name').value = "";
            document.getElementById('surname').value = "";
        }
        document.getElementById('mainWeight').value = "";
        document.getElementById('mainHeight').value = "";
        document.getElementById('age').value = "";
        document.getElementById('gender').value = "male";
        document.getElementById('activity').value = "1.2";
        document.getElementById('dietType').value = "balanced";
        updateMacroLabels();
        const neckEl = document.getElementById('neckCirc');
        const waistEl = document.getElementById('waistCirc');
        const hipEl = document.getElementById('hipCirc');
        if (neckEl) neckEl.value = "";
        if (waistEl) waistEl.value = "";
        if (hipEl) hipEl.value = "";
        resultsArea.classList.add('hidden');
        if (reportBtnArea) reportBtnArea.classList.add('hidden');
        document.getElementById('gaugePointer').style.left = '0%';
        document.getElementById('bodyFatCard')?.classList.add('hidden');
        document.getElementById('whtrCard')?.classList.add('hidden');
        document.getElementById('velocityCard')?.classList.add('hidden');
        document.getElementById('projectionCard')?.classList.add('hidden');
    }

    // --- PDF Report Generation ---
    async function generatePDFReport() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const pageWidth = doc.internal.pageSize.getWidth();
        const d = lastCalcData;

        // Title
        doc.setFontSize(22);
        doc.setTextColor(0, 115, 170);
        doc.text('Health Assessment Report', pageWidth / 2, 20, { align: 'center' });

        doc.setFontSize(11);
        doc.setTextColor(100, 100, 100);
        const userName = window.BMI_USER ? window.BMI_USER.name : (document.getElementById('name').value || 'Anonymous');
        doc.text(`${userName} | ${new Date().toLocaleDateString()}`, pageWidth / 2, 28, { align: 'center' });

        let y = 40;

        // BMI Section
        doc.setFontSize(16);
        doc.setTextColor(0, 0, 0);
        doc.text('Body Mass Index (BMI)', 15, y);
        y += 8;
        doc.setFontSize(12);
        doc.text(`BMI: ${d.bmi} (${d.category})`, 20, y); y += 6;
        doc.text(`Weight: ${d.weight} kg | Height: ${d.height} cm`, 20, y); y += 6;
        doc.text(`Ideal Weight Range: ${d.idealRange}`, 20, y); y += 10;

        // Body Fat
        if (d.bodyFatPct) {
            doc.setFontSize(16);
            doc.text('Body Composition', 15, y); y += 8;
            doc.setFontSize(12);
            doc.text(`Body Fat: ${d.bodyFatPct}% (Navy Method)`, 20, y); y += 6;
            if (d.whtr) doc.text(`Waist-to-Height Ratio: ${d.whtr}`, 20, y); y += 10;
        }

        // Energy
        doc.setFontSize(16);
        doc.text('Energy & Nutrition', 15, y); y += 8;
        doc.setFontSize(12);
        doc.text(`BMR: ${d.bmr} kcal/day | TDEE: ${d.tdee} kcal/day`, 20, y); y += 6;
        doc.text(`Protein: ${d.protein} | Carbs: ${d.carbs} | Fats: ${d.fats}`, 20, y); y += 12;

        // Chart image
        const chartCanvas = document.getElementById('bmiChart');
        if (chartCanvas && !chartContainer.classList.contains('hidden')) {
            try {
                const chartImage = await html2canvas(chartContainer, { backgroundColor: '#ffffff' });
                const imgData = chartImage.toDataURL('image/png');
                const imgWidth = pageWidth - 30;
                const imgHeight = (chartImage.height / chartImage.width) * imgWidth;
                if (y + imgHeight > 280) { doc.addPage(); y = 20; }
                doc.setFontSize(16);
                doc.text('Progress Chart', 15, y); y += 8;
                doc.addImage(imgData, 'PNG', 15, y, imgWidth, Math.min(imgHeight, 80));
                y += Math.min(imgHeight, 80) + 10;
            } catch (e) {
                console.error("Chart capture failed:", e);
            }
        }

        // Disclaimer
        if (y > 260) { doc.addPage(); y = 20; }
        doc.setFontSize(9);
        doc.setTextColor(150, 150, 150);
        doc.text('This report is for informational purposes only and does not constitute medical advice.', pageWidth / 2, 285, { align: 'center' });

        doc.save('health-report.pdf');
    }

    // --- Unit Converters ---
    // Weight
    const kgIn = document.getElementById('kgInput');
    const lbsIn = document.getElementById('lbsInput');
    const gmIn = document.getElementById('gmInput');
    const ozIn = document.getElementById('ozInput');

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

    // Height
    const cmIn = document.getElementById('cmInput');
    const mIn = document.getElementById('mInput');
    const inIn = document.getElementById('inchInput');
    const ftIn = document.getElementById('ftInput');

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
});
