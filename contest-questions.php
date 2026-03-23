<?php
session_start();

// Check if user came from the contest form submission
if (!isset($_SESSION['contest_entry_id']) || !isset($_GET['entry_id'])) {
    header("Location: index.php#contest");
    exit;
}

$entry_id = $_GET['entry_id'];
if ($entry_id != $_SESSION['contest_entry_id']) {
    header("Location: index.php#contest");
    exit;
}

// Clear the session variable so they can't access this page again
unset($_SESSION['contest_entry_id']);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eDO Soutěž - Otázky</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏆</text></svg>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .contest-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .contest-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .contest-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .contest-header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .contest-content {
            padding: 40px 30px;
        }

        .question {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #667eea;
        }

        .question h3 {
            color: #495057;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .question-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .options {
            display: grid;
            gap: 12px;
        }

        .option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .option:hover {
            border-color: #667eea;
            background: #f0f3ff;
            transform: translateX(5px);
        }

        .option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        .option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
        }

        .option-text {
            flex: 1;
            font-weight: 500;
        }

        .submit-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-top: 30px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .submit-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #f0f3ff;
            transform: translateX(-5px);
        }

        .progress-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            margin: 30px 0;
            overflow: hidden;
        }

        .progress {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .status-message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .contest-container {
                margin: 10px;
            }
            
            .contest-header {
                padding: 30px 20px;
            }
            
            .contest-header h1 {
                font-size: 2em;
            }
            
            .contest-content {
                padding: 30px 20px;
            }
            
            .question {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="contest-container">
        <div class="contest-header">
            <h1><i class="fas fa-trophy"></i> eDO Soutěž</h1>
            <p>Odpovězte na otázky a vyhrajte skvělé ceny!</p>
        </div>

        <div class="contest-content">
            <a href="index.php#contest" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Zpět na hlavní stránku
            </a>

            <div id="status-messages"></div>

            <form id="contestQuestionsForm">
                <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry_id) ?>">

                <!-- Question 1 -->
                <div class="question">
                    <h3>
                        <span class="question-number">1</span>
                        Co představuje tzv. LTV (Loan to Value) u hypotéčního úvěru?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question1" value="a" required>
                            <span class="option-text">Poměr mezi výší hypotéčního úvěru a aktuální tržní hodnotou zastavené nemovitosti.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question1" value="b" required>
                            <span class="option-text">Poměr mezi splátkou úvěru a měsíčním příjmem žadatele.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question1" value="c" required>
                            <span class="option-text">Poměr mezi úrokovou sazbou a poplatky spojenými s úvěrem.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question1" value="d" required>
                            <span class="option-text">Poměr mezi celkovou výší úvěru a ročním příjmem žadatele.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 2 -->
                <div class="question">
                    <h3>
                        <span class="question-number">2</span>
                        Který z následujících pojmů přesně definuje hypotéční úvěr?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question2" value="a" required>
                            <span class="option-text">Dlouhodobý spotřebitelský úvěr s maximální splatností 30 let.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question2" value="b" required>
                            <span class="option-text">Úvěr zajištěný nemovitostí klienta, který nemusí být určen na bydlení.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question2" value="c" required>
                            <span class="option-text">Úvěr, který je zajištěn zástavním právem k nemovité věci a jehož účelem je financování jakékoli potřeby klienta, nikoli nutně bydlení.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question2" value="d" required>
                            <span class="option-text">Úvěr zajištěný nemovitostí, který je vždy účelově vázán na nákup, stavbu nebo rekonstrukci rezidenčního bydlení.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 3 -->
                <div class="question">
                    <h3>
                        <span class="question-number">3</span>
                        Co je hlavním rizikem pro dlužníka spojeným s koncem období fixace úrokové sazby hypotéky?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question3" value="a" required>
                            <span class="option-text">Automatické prodloužení stávající úrokové sazby bez možnosti její změny.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question3" value="b" required>
                            <span class="option-text">Zvýšení měsíční splátky v důsledku možné změny tržních úrokových sazeb.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question3" value="c" required>
                            <span class="option-text">Povinnost splatit celou zbývající jistinu úvěru najednou.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question3" value="d" required>
                            <span class="option-text">Zrušení hypotečního úvěru ze strany banky kvůli nedostatečné bonitě.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 4 -->
                <div class="question">
                    <h3>
                        <span class="question-number">4</span>
                        Který z následujících typů hypoték je typický pro svůj neúčelový charakter?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question4" value="a" required>
                            <span class="option-text">Americká hypotéka (American Mortgage).</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question4" value="b" required>
                            <span class="option-text">Ekologická hypotéka (Green Mortgage).</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question4" value="c" required>
                            <span class="option-text">Hypotéka s variabilní úrokovou sazbou.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question4" value="d" required>
                            <span class="option-text">Hypotéka na pořízení družstevního bytu.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 5 -->
                <div class="question">
                    <h3>
                        <span class="question-number">5</span>
                        Co je hlavním účelem pojištění schopnosti splácet (PPI) u hypotéky?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question5" value="a" required>
                            <span class="option-text">Krýt měsíční splátky úvěru v případě dlouhodobé nemoci, ztráty zaměstnání nebo úmrtí dlužníka.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question5" value="b" required>
                            <span class="option-text">Pokrýt náklady na notářské poplatky spojené se zřízením zástavního práva.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question5" value="c" required>
                            <span class="option-text">Zajistit dlužníkovi pravidelný příjem v případě změny úrokové sazby.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question5" value="d" required>
                            <span class="option-text">Chránit banku proti poklesu hodnoty zastavené nemovitosti.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 6 -->
                <div class="question">
                    <h3>
                        <span class="question-number">6</span>
                        Jaký ekonomický nástroj centrální banka nejčastěji využívá k ovlivňování úrokových sazeb hypoték a ochlazování trhu s bydlením?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question6" value="a" required>
                            <span class="option-text">Přímá cenová regulace nemovitostí.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question6" value="b" required>
                            <span class="option-text">Změna limitu DSTI pro komerční banky.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question6" value="c" required>
                            <span class="option-text">Intervence na devizovém trhu.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question6" value="d" required>
                            <span class="option-text">Stanovení základních úrokových sazeb (např. REPO sazby).</span>
                        </label>
                    </div>
                </div>

                <!-- Question 7 -->
                <div class="question">
                    <h3>
                        <span class="question-number">7</span>
                        Pokud si klient sjedná hypotéku s variabilní úrokovou sazbou, co ji bude ovlivňovat v průběhu splácení?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question7" value="a" required>
                            <span class="option-text">Výhradně smluvně sjednaná marže banky.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question7" value="b" required>
                            <span class="option-text">Pohyb inflace a ceny stavebních materiálů.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question7" value="c" required>
                            <span class="option-text">Rozhodnutí hypotečního specialisty banky v každém kalendářním roce.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question7" value="d" required>
                            <span class="option-text">Tržní úrokové sazby (např. EURIBOR nebo PRIBOR) a marže banky.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 8 -->
                <div class="question">
                    <h3>
                        <span class="question-number">8</span>
                        Který z následujících dokumentů je klíčový pro ověření hodnoty nemovitosti při sjednávání hypotéky?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question8" value="a" required>
                            <span class="option-text">Výpis z rejstříku trestů žadatele.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question8" value="b" required>
                            <span class="option-text">Odhad tržní ceny nemovitosti (znalecký posudek) vyhotovený bankou nebo akceptovaným odhadcem.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question8" value="c" required>
                            <span class="option-text">Potvrzení o výši příjmu od zaměstnavatele.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question8" value="d" required>
                            <span class="option-text">Kupní smlouva ověřená notářem.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 9 -->
                <div class="question">
                    <h3>
                        <span class="question-number">9</span>
                        Co znamená termín 'refinancování hypotéky'?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question9" value="a" required>
                            <span class="option-text">Nahrazení stávajícího hypotečního úvěru novým úvěrem, obvykle s lepšími podmínkami u téže nebo jiné banky.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question9" value="b" required>
                            <span class="option-text">Pojištění úvěru pro případ neschopnosti splácet.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question9" value="c" required>
                            <span class="option-text">Předčasné splacení celého úvěru z vlastních finančních prostředků klienta.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question9" value="d" required>
                            <span class="option-text">Navýšení stávajícího hypotečního úvěru na původní nemovitosti.</span>
                        </label>
                    </div>
                </div>

                <!-- Question 10 -->
                <div class="question">
                    <h3>
                        <span class="question-number">10</span>
                        Která z těchto součástí není přímou součástí RPSN (Roční Procentní Sazba Nákladů) u hypotéky?
                    </h3>
                    <div class="options">
                        <label class="option">
                            <input type="radio" name="question10" value="a" required>
                            <span class="option-text">Poplatek za zpracování úvěru.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question10" value="b" required>
                            <span class="option-text">Úroková sazba úvěru.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question10" value="c" required>
                            <span class="option-text">Poplatek za vedení úvěrového účtu.</span>
                        </label>
                        <label class="option">
                            <input type="radio" name="question10" value="d" required>
                            <span class="option-text">Poplatky za návrh na vklad zástavního práva do katastru nemovitostí.</span>
                        </label>
                    </div>
                </div>

                <div class="progress-bar">
                    <div class="progress" id="progressBar"></div>
                </div>

                <div class="submit-section">
                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        Odeslat odpovědi
                    </button>
                    <p style="margin-top: 15px; color: #6c757d; font-size: 14px;">
                        Ujistěte se, že jste odpověděli na všechny otázky před odesláním.
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('contestQuestionsForm');
            const progressBar = document.getElementById('progressBar');
            const submitBtn = document.getElementById('submitBtn');
            
            // Handle option selection visual feedback
            document.querySelectorAll('.option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    const questionName = radio.name;
                    
                    // Remove selected class from all options in this question
                    document.querySelectorAll(`input[name="${questionName}"]`).forEach(r => {
                        r.closest('.option').classList.remove('selected');
                    });
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    radio.checked = true;
                    
                    updateProgress();
                });
            });
            
            function updateProgress() {
                const totalQuestions = 10; // Updated to 10 questions
                const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
                const progress = (answeredQuestions / totalQuestions) * 100;
                
                progressBar.style.width = progress + '%';
                
                // Enable submit button when all questions are answered
                if (answeredQuestions === totalQuestions) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.6';
                }
            }
            
            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const originalText = submitBtn.innerHTML;
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Odesílá se...';
                submitBtn.disabled = true;
                
                fetch('contest-answers-handler.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Failed to parse JSON:', e);
                            console.error('Raw response text:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed response:', data);
                    if (data.success) {
                        showStatusMessage('success', data.message || 'Odpovědi byly úspěšně odeslány!');
                        setTimeout(() => {
                            window.location.href = 'contest-results.php?entry_id=' + formData.get('entry_id');
                        }, 2000);
                    } else {
                        showStatusMessage('error', data.message || 'Chyba při odesílání odpovědí');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatusMessage('error', 'Chyba při komunikaci se serverem: ' + error.message);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
            
            function showStatusMessage(type, message) {
                const container = document.getElementById('status-messages');
                const div = document.createElement('div');
                div.className = `status-message status-${type}`;
                div.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
                
                container.innerHTML = '';
                container.appendChild(div);
                
                setTimeout(() => {
                    if (div.parentNode) {
                        div.remove();
                    }
                }, 5000);
            }
            
            // Initialize progress
            updateProgress();
        });
    </script>
</body>
</html>
