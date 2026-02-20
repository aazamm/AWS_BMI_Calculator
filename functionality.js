document.addEventListener('DOMContentLoaded', function () {
    
    // --- Theme Toggle ---
    const themeBtn = document.getElementById('themeBtn');
    const body = document.body;

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeBtn.innerText = '☀️ Light Mode';
    }

    themeBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        themeBtn.innerText = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
    });

    // --- Main Calculator Logic ---
    const calcBtn = document.getElementById('calcBtn');
    const clearBtn = document.getElementById('clearBtn');
    const resultsArea = document.getElementById('resultsArea');
    const historyArea = document.getElementById('historyArea');
    const historyBody = document.getElementById('historyBody');
    const userSelect = document.getElementById('userSelect');
    const chartContainer = document.getElementById('chartContainer');
    const exportBtn = document.getElementById('exportBtn');
    let bmiChartInstance = null;
    let currentFilteredHistory = [];

    userSelect.addEventListener('change', loadHistory);
    exportBtn.addEventListener('click', exportToCSV);
    
    loadHistory();

    calcBtn.addEventListener('click', calculateBMI);
    clearBtn.addEventListener('click', clearForm);

    function exportToCSV() {
        if (currentFilteredHistory.length === 0) {
            alert("No records to export.");
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
            alert("Please enter valid positive numbers for weight, height, and age.");
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

        // Macros
        document.getElementById('proteinResult').innerText = ((tdee * 0.30) / 4).toFixed(0) + 'g';
        document.getElementById('carbsResult').innerText = ((tdee * 0.40) / 4).toFixed(0) + 'g';
        document.getElementById('fatsResult').innerText = ((tdee * 0.30) / 9).toFixed(0) + 'g';

        // Gauge
        let percent = ((bmiValue - 15) / (40 - 15)) * 100;
        document.getElementById('gaugePointer').style.left = Math.max(0, Math.min(100, percent)) + '%';

        const commentEl = document.getElementById('commentResult');
        let statusClass = "";
        let commentText = "";

        if (bmiValue < 18.5) { commentText = "Underweight"; statusClass = "status-underweight"; }
        else if (bmiValue <= 24.9) { commentText = "Normal weight"; statusClass = "status-normal"; }
        else if (bmiValue < 30) { commentText = "Overweight"; statusClass = "status-overweight"; }
        else { commentText = "Obese"; statusClass = "status-obese"; }

        commentEl.innerText = commentText;
        commentEl.className = "result-value " + statusClass;
        document.getElementById('bmiResult').className = "result-value " + statusClass;

        resultsArea.classList.remove('hidden');

        // Save to Database via PHP
        const fullName = `${nameInput} ${surnameInput}`.trim() || 'Anonymous';
        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save',
                    name: nameInput || 'Anonymous',
                    surname: surnameInput,
                    weight_kg: kilograms.toFixed(2),
                    height_cm: centimeters.toFixed(2)
                })
            });
            const resData = await response.json();
            if (resData.success) {
                // If it's a new user, we might want to update the dropdown first
                await loadHistory();
                userSelect.value = fullName;
                loadHistory(); // Reload to filter for this user
            }
        } catch (err) {
            console.error("Failed to save record:", err);
        }
    }

    async function loadHistory() {
        try {
            const response = await fetch('index.php?action=get_history');
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
        const ctx = document.getElementById('bmiChart').getContext('2d');
        if (bmiChartInstance) bmiChartInstance.destroy();
        bmiChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: `BMI Progress for ${user}`,
                    data: bmiValues,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function clearForm() {
        document.getElementById('name').value = "";
        document.getElementById('surname').value = "";
        document.getElementById('mainWeight').value = "";
        document.getElementById('mainHeight').value = "";
        document.getElementById('age').value = "";
        document.getElementById('gender').value = "male";
        document.getElementById('activity').value = "1.2";
        resultsArea.classList.add('hidden');
        document.getElementById('gaugePointer').style.left = '0%';
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