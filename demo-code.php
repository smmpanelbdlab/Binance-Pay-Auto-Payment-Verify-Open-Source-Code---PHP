<?php
/**
 * Binance Pay Demo - Public Test Page
 * 
 * This page demonstrates the complete Binance Pay verification flow:
 * 1. Enter amount (and optionally a license key)
 * 2. Click "Pay Now" → opens the payment modal popup
 * 3. Modal shows Binance ID, QR code, currency from the license
 * 4. User enters Order ID from Binance and verifies
 * 5. Result shown in the modal (success or error)
 * 
 * No database required – uses your licensing API only.
 * 
 * HOW TO USE:
 * - Place this file on any PHP server with cURL enabled.
 * - If you have a license key, enter it in the input field.
 * - If left empty, a default test license key will be used (if defined below).
 */

define('API_BASE_URL', 'https://panel.smmpanelbdlab.com/api');

// Default license key (optional) – replace with your own for testing
// If you want users to supply their own key, you can leave this empty.
$default_license_key = 'YOUR_LICENSE_KEY_HERE';

// ============================================================
// HANDLE AJAX VERIFICATION REQUEST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // ---- Get Binance credentials for a license key ----
    if ($action === 'fetch_credentials') {
        $license_key = trim($_POST['license_key'] ?? '');
        if (empty($license_key)) {
            echo json_encode(['success' => false, 'message' => 'License key is required.']);
            exit;
        }

        $url = API_BASE_URL . '/verify-license?license=' . urlencode($license_key);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            echo json_encode(['success' => false, 'message' => 'License server error. Please try again.']);
            exit;
        }

        $data = json_decode($response, true);
        if (!$data || !$data['success']) {
            echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Invalid license key.']);
            exit;
        }

        // Return the credentials plus plan expiry
        echo json_encode([
            'success' => true,
            'data' => $data['data']
        ]);
        exit;
    }

    // ---- Verify a payment (Order ID) ----
    if ($action === 'verify_payment') {
        $license_key = trim($_POST['license_key'] ?? '');
        $order_id    = trim($_POST['order_id'] ?? '');
        $amount      = floatval($_POST['amount'] ?? 0);

        if (empty($license_key) || empty($order_id) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
            exit;
        }

        if (!preg_match('/^\d{15,22}$/', $order_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Order ID format. Must be 15-22 digits.']);
            exit;
        }

        $url = API_BASE_URL . '/verify-payment';
        $post_data = [
            'license'  => $license_key,
            'order_id' => $order_id,
            'amount'   => $amount
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || !$response) {
            echo json_encode(['success' => false, 'message' => 'Payment server error. Please try again.']);
            exit;
        }

        $result = json_decode($response, true);
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Invalid API response.']);
            exit;
        }

        echo json_encode($result);
        exit;
    }

    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Binance Pay Demo - SMM Panel BD Lab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
  #bnp-modal,body{font-family:'Segoe UI',system-ui,sans-serif}*{margin:0;padding:0;box-sizing:border-box}body{background:linear-gradient(135deg,#f5f7fa 0,#e6e9f0 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.demo-container{max-width:520px;width:100%}.demo-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:2rem 1.8rem;box-shadow:0 10px 30px rgba(0,0,0,.08);text-align:center}.demo-card .header-icon{font-size:2.2rem;color:#f0b90b;margin-bottom:.5rem}.demo-card h2{font-weight:700;color:#1a202c;margin-bottom:.2rem}.demo-card .subtitle{color:#64748b;font-size:.95rem;margin-bottom:1.5rem}.form-group{margin-bottom:1rem;text-align:left}.form-group label{font-weight:600;color:#374151;display:block;margin-bottom:.3rem;font-size:.9rem}.form-group input{width:100%;padding:.6rem .9rem;border:1px solid #d1d5db;border-radius:8px;font-size:1rem;transition:.2s}.form-group input:focus{border-color:#f0b90b;outline:0;box-shadow:0 0 0 3px rgba(240,185,11,.2)}.form-group .help-text{font-size:.8rem;color:#6b7280;margin-top:.2rem}.btn-pay{display:inline-block;width:100%;padding:14px;background:linear-gradient(135deg,#f0b90b 0,#d4a50a 100%);color:#1e2329;font-weight:700;font-size:1.1rem;border:none;border-radius:50px;cursor:pointer;transition:.3s;box-shadow:0 4px 15px rgba(240,185,11,.3);margin-top:.5rem}.btn-pay:hover{transform:translateY(-2px);box-shadow:0 6px 25px rgba(240,185,11,.4)}.btn-pay:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none}.btn-pay i{margin-right:8px}.powered-by{margin-top:1.5rem;font-size:.8rem;color:#94a3b8}.powered-by a{color:#3b82f6;text-decoration:none}.powered-by a:hover{text-decoration:underline}#bnp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99998;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px);touch-action:none;overscroll-behavior:contain}#bnp-overlay.active{display:flex}#bnp-modal{background:#fff;border-radius:12px;width:100%;max-width:440px;max-height:92vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 64px rgba(0,0,0,.25);position:relative;animation:.25s modalFade}@keyframes modalFade{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}.bnp-modal-head{padding:18px 20px 14px;flex-shrink:0;background:#fff;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between}.bnp-modal-title{font-size:18px;font-weight:700;color:#111827;display:flex;align-items:center;gap:8px}.bnp-modal-title i{color:#f0b90b}.bnp-close-btn{background:0 0;border:none;cursor:pointer;color:#9ca3af;padding:6px;border-radius:50%;line-height:1;display:flex;align-items:center;justify-content:center;transition:.2s;font-size:1.4rem}.bnp-close-btn:hover{background:#f3f4f6;color:#374151}.bnp-modal-scroll{overflow-y:auto;flex:1;padding:0 20px 20px}.bnp-step-circle,.bnp-stepper{align-items:center;display:flex}.bnp-modal-scroll::-webkit-scrollbar{width:5px}.bnp-modal-scroll::-webkit-scrollbar-track{background:0 0}.bnp-modal-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:99px}.bnp-modal-scroll::-webkit-scrollbar-thumb:hover{background:#9ca3af}.bnp-stepper{justify-content:center;margin:16px 0 12px;gap:0}.bnp-step-item{display:flex;flex-direction:column;align-items:center}.bnp-step-circle{width:28px;height:28px;border-radius:50%;justify-content:center;font-size:13px;font-weight:700;font-family:Arial,sans-serif;transition:.3s;background:#fff;border:2px solid #d1d5db;color:#9ca3af}.bnp-step-circle.active{background:#2563eb;border-color:#2563eb;color:#fff}.bnp-step-circle.done{background:#fff;border-color:#d1d5db;color:#9ca3af}.bnp-step-label{font-size:10px;font-weight:500;color:#9ca3af;margin-top:4px;text-align:center}.bnp-step-label.active{color:#2563eb;font-weight:600}.bnp-step-line{width:50px;height:1px;background:#d1d5db;margin-bottom:18px}.bnp-step-line.active{background:#2563eb}.bnp-body{padding:0 0 12px}.bnp-amount-big{font-size:28px;font-weight:700;color:#111827;text-align:center;margin:8px 0 12px}.bnp-amount-big span{font-size:16px;color:#6b7280;font-weight:400;margin-left:4px}.bnp-id-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:4px}.bnp-id-row,.bnp-instructions,.bnp-qr-box,.bnp-summary{margin-bottom:12px}.bnp-id-row{display:flex;align-items:center;border:1px solid #d1d5db;border-radius:8px;overflow:hidden}.bnp-id-val{flex:1;padding:10px 12px;font-size:14px;font-weight:500;color:#111827;background:#f9fafb;font-family:monospace}.bnp-confirm-btn,.bnp-copy-btn{font-weight:600;cursor:pointer;transition:.2s}.bnp-copy-btn{padding:10px 14px;background:#fff;border:none;border-left:1px solid #d1d5db;font-size:13px;color:#374151;white-space:nowrap}.bnp-copy-btn:hover,.bnp-qr-box{background:#f3f4f6}.bnp-qr-box{border-radius:10px;padding:12px;text-align:center}.bnp-qr-img{max-width:160px;border-radius:8px;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.1)}.bnp-instructions{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;font-size:13px;color:#374151;text-align:left}.bnp-hint ol,.bnp-instructions ol{padding-left:16px;margin:4px 0 0}.bnp-hint li,.bnp-instructions li{margin-bottom:2px}.bnp-confirm-btn{width:100%;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:14px}.bnp-confirm-btn:hover,.bnp-verify-btn:hover{background:#1d4ed8}.bnp-summary{display:flex;gap:6px}.bnp-sum-item{flex:1;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;text-align:center}.bnp-sum-label{font-size:10px;color:#9ca3af;font-weight:500;text-transform:uppercase;letter-spacing:.04em}.bnp-sum-val{font-size:13px;font-weight:700;color:#111827;display:flex;align-items:center;justify-content:center;gap:4px}.bnp-order-input{width:100%;padding:10px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:14px;color:#111827;outline:0;transition:.2s;background:#fff;text-align:center;font-family:monospace;letter-spacing:1px}.bnp-order-input:focus{border-color:#2563eb}.bnp-order-input:disabled{background:#f3f4f6;color:#9ca3af}.bnp-err-msg,.bnp-ok-msg{font-size:13px;margin-top:8px;padding:8px 12px;border-radius:6px;display:none}.bnp-err-msg{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}.bnp-ok-msg{background:#f0fdf4;border:1px solid #86efac;color:#15803d}.bnp-verify-btn{width:100%;padding:11px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-top:10px;transition:.2s}.bnp-verify-btn:disabled{background:#93c5fd;cursor:not-allowed}.bnp-spinner{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:.7s linear infinite spin;vertical-align:middle;margin-right:6px}@keyframes spin{to{transform:rotate(360deg)}}.bnp-hint{background:#f3f4f6;border-radius:8px;padding:10px 12px;margin:8px 0;font-size:12px;color:#374151;text-align:left}.demo-alert{padding:12px 16px;border-radius:8px;margin-bottom:1rem;font-size:.95rem;display:none}.demo-alert.success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;display:block}.demo-alert.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;display:block}@media (max-width:480px){.demo-card{padding:1.5rem 1rem}.bnp-step-line{width:30px}.bnp-summary{flex-wrap:wrap}}
    </style>
</head>
<body>

<div class="demo-container">

    <div class="demo-card">
        <div class="header-icon"><i class="fas fa-credit-card"></i></div>
        <h2>Binance Pay Demo</h2>
        <p class="subtitle">Test the payment verification flow with a live license key</p>

        <!-- ===== ALERT PLACEHOLDER ===== -->
        <div id="demoAlert" class="demo-alert"></div>

        <!-- ===== FORM ===== -->
        <form id="paymentForm" onsubmit="return false;">
            <div class="form-group">
                <label for="licenseKey"><i class="fas fa-key"></i> License Key</label>
                <input type="text" id="licenseKey" placeholder="Enter your license key" 
                       value="<?php echo htmlspecialchars($default_license_key); ?>" required>
                <div class="help-text">Your license key from the SMM Panel BD Lab dashboard.</div>
            </div>

            <div class="form-group">
                <label for="amountInput"><i class="fas fa-dollar-sign"></i> Amount (USD)</label>
                <input type="number" id="amountInput" placeholder="0.00" step="0.01" min="0.01" value="5.00" required>
                <div class="help-text">Enter the amount you wish to pay (will be shown in the modal).</div>
            </div>

            <button type="button" class="btn-pay" id="payBtn" onclick="startPayment()">
                <i class="fas fa-arrow-right"></i> Pay Now
            </button>
        </form>

        <div class="powered-by">
            Powered by <a href="https://panel.smmpanelbdlab.com" target="_blank">SMM Panel BD Lab</a>
        </div>
    </div>

    <!-- ==========================================================
           MODAL (SMM SCRIPT STYLE)
           ========================================================== -->
    <div id="bnp-overlay">
        <div id="bnp-modal">
            <!-- Header -->
            <div class="bnp-modal-head">
                <div class="bnp-modal-title">
                    <i class="fas fa-credit-card"></i> Binance Pay
                </div>
                <button class="bnp-close-btn" onclick="closeModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="bnp-modal-scroll">
                <!-- Stepper -->
                <div class="bnp-stepper">
                    <div class="bnp-step-item">
                        <div class="bnp-step-circle active" id="step1-circle">1</div>
                        <div class="bnp-step-label active" id="step1-label">Make payment</div>
                    </div>
                    <div class="bnp-step-line" id="step-line"></div>
                    <div class="bnp-step-item">
                        <div class="bnp-step-circle" id="step2-circle">2</div>
                        <div class="bnp-step-label" id="step2-label">Verify payment</div>
                    </div>
                </div>

                <!-- Step 1 -->
                <div class="bnp-body" id="step1-body">
                    <div class="bnp-amount-big" id="modalAmountDisplay">
                        0.00 <span>USD</span>
                    </div>

                    <div class="bnp-id-label">Send to Binance ID</div>
                    <div class="bnp-id-row">
                        <div class="bnp-id-val" id="modalBinanceId">Loading...</div>
                        <button class="bnp-copy-btn" onclick="copyBinanceId()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>

                    <div class="bnp-qr-box" id="qrContainer" style="display:none;">
                        <div style="font-size:11px;color:#6b7280;margin-bottom:6px;">Scan with Binance App to pay</div>
                        <img id="qrImage" src="" class="bnp-qr-img" alt="QR">
                    </div>

                    <div class="bnp-instructions">
                        <ol>
                            <li>Scan the QR or send funds to the Binance ID above.</li>
                            <li>After completing the payment, tap <strong>Confirm payment</strong>.</li>
                        </ol>
                    </div>

                    <button class="bnp-confirm-btn" onclick="goToStep2()">Confirm payment</button>
                </div>

                <!-- Step 2 -->
                <div class="bnp-body" id="step2-body" style="display:none; padding-top:6px;">
                    <div class="bnp-summary">
                        <div class="bnp-sum-item">
                            <div class="bnp-sum-label">Amount</div>
                            <div class="bnp-sum-val" id="modalSummaryAmount">0.00 USD</div>
                        </div>
                        <div class="bnp-sum-item">
                            <div class="bnp-sum-label">Send to</div>
                            <div class="bnp-sum-val">
                                <span id="modalSummaryBinanceId">...</span>
                                <button class="bnp-copy-btn" style="background:none;border:none;padding:0 4px;font-size:12px;color:#2563eb;" onclick="copyBinanceId()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bnp-order-label" style="font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">
                        Enter your Binance Order ID
                    </div>
                    <input type="text" class="bnp-order-input" id="bnp-oid"
                           placeholder="e.g. 418542647623835648"
                           maxlength="22" autocomplete="off">

                    <div class="bnp-hint" style="margin-top:6px;">
                        <strong>Where to find Order ID?</strong>
                        <ol>
                            <li>Open your Binance app → Wallet → Transaction History.</li>
                            <li>Find the payment you just made and copy the <strong>Order ID</strong>.</li>
                            <li>Paste it above and tap <strong>Verify payment</strong>.</li>
                        </ol>
                    </div>

                    <div class="bnp-err-msg" id="bnp-err"></div>
                    <div class="bnp-ok-msg" id="bnp-ok"></div>

                    <button class="bnp-verify-btn" id="bnp-vbtn" onclick="verifyPayment()">
                        Verify payment
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ==========================================================
     JAVASCRIPT
     ========================================================== -->
<script>
// ===== STATE VARIABLES =====
var licenseKey = '';
var amount = 0;
var currency = 'USDT';
var binanceId = '';
var qrUrl = '';
var isFixed = false; // always true for this demo (amount fixed after click)

// ===== UI HELPERS =====
function showAlert(message, type) {
    var alertEl = document.getElementById('demoAlert');
    alertEl.textContent = message;
    alertEl.className = 'demo-alert ' + type;
    alertEl.style.display = 'block';
}
function hideAlert() {
    document.getElementById('demoAlert').style.display = 'none';
}

function showToast(message) {
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#f0b90b;color:#1e2329;padding:12px 24px;border-radius:8px;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideUp 0.3s ease;';
    toast.textContent = '✓ ' + message;
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        setTimeout(function() { document.body.removeChild(toast); }, 500);
    }, 3000);
}

function copyBinanceId() {
    var text = document.getElementById('modalBinanceId').textContent.trim();
    if (!text || text === 'Loading...') return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Binance ID copied!');
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('Binance ID copied!');
    }
}

// ===== START PAYMENT =====
function startPayment() {
    hideAlert();
    var licenseInput = document.getElementById('licenseKey');
    var amountInput = document.getElementById('amountInput');
    var payBtn = document.getElementById('payBtn');

    licenseKey = licenseInput.value.trim();
    amount = parseFloat(amountInput.value);

    if (!licenseKey) {
        showAlert('Please enter a valid license key.', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        showAlert('Please enter a valid amount.', 'error');
        return;
    }

    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    // Step 1: Fetch credentials from API
    var formData = new FormData();
    formData.append('action', 'fetch_credentials');
    formData.append('license_key', licenseKey);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Pay Now';

        if (!data.success) {
            showAlert(data.message || 'Failed to fetch credentials.', 'error');
            return;
        }

        // Store data
        var creds = data.data;
        binanceId = creds.binance_email_id || '';
        currency = creds.currency || 'USDT';
        qrUrl = creds.qrcode_url || '';

        // Fill modal with details
        document.getElementById('modalAmountDisplay').innerHTML = amount.toFixed(2) + ' <span>' + currency + '</span>';
        document.getElementById('modalSummaryAmount').textContent = amount.toFixed(2) + ' ' + currency;
        document.getElementById('modalBinanceId').textContent = binanceId;
        document.getElementById('modalSummaryBinanceId').textContent = binanceId;

        // QR code
        var qrContainer = document.getElementById('qrContainer');
        var qrImg = document.getElementById('qrImage');
        if (qrUrl && isValidUrl(qrUrl)) {
            qrImg.src = qrUrl;
            qrContainer.style.display = 'block';
        } else {
            qrContainer.style.display = 'none';
        }

        // Open modal
        document.getElementById('bnp-overlay').classList.add('active');
        document.getElementById('bnp-overlay').style.display = 'flex';
        // Reset to step 1
        goToStep1();

        hideAlert();
    })
    .catch(function(error) {
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Pay Now';
        showAlert('Network error: ' + error.message, 'error');
    });
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// ===== MODAL =====
function closeModal() {
    document.getElementById('bnp-overlay').classList.remove('active');
    document.getElementById('bnp-overlay').style.display = 'none';
    goToStep1();
}

function goToStep1() {
    document.getElementById('step1-body').style.display = 'block';
    document.getElementById('step2-body').style.display = 'none';
    document.getElementById('step1-circle').className = 'bnp-step-circle active';
    document.getElementById('step2-circle').className = 'bnp-step-circle';
    document.getElementById('step1-label').className = 'bnp-step-label active';
    document.getElementById('step2-label').className = 'bnp-step-label';
    document.getElementById('step-line').className = 'bnp-step-line';
    // Reset error/success
    document.getElementById('bnp-err').style.display = 'none';
    document.getElementById('bnp-ok').style.display = 'none';
    document.getElementById('bnp-vbtn').style.display = 'block';
    document.getElementById('bnp-vbtn').disabled = false;
    document.getElementById('bnp-vbtn').innerHTML = 'Verify payment';
    document.getElementById('bnp-oid').disabled = false;
    document.getElementById('bnp-oid').value = '';
    document.querySelector('.bnp-modal-scroll').scrollTop = 0;
}

function goToStep2() {
    document.getElementById('step1-body').style.display = 'none';
    document.getElementById('step2-body').style.display = 'block';
    document.getElementById('step1-circle').className = 'bnp-step-circle done';
    document.getElementById('step2-circle').className = 'bnp-step-circle active';
    document.getElementById('step1-label').className = 'bnp-step-label';
    document.getElementById('step2-label').className = 'bnp-step-label active';
    document.getElementById('step-line').className = 'bnp-step-line active';
    document.querySelector('.bnp-modal-scroll').scrollTop = 0;
    document.getElementById('bnp-oid').focus();
}

// ===== VERIFY PAYMENT =====
function verifyPayment() {
    var oid = document.getElementById('bnp-oid').value.trim();
    var err = document.getElementById('bnp-err');
    var ok = document.getElementById('bnp-ok');
    var btn = document.getElementById('bnp-vbtn');

    err.style.display = 'none';
    ok.style.display = 'none';

    if (!oid || !/^\d{15,22}$/.test(oid)) {
        err.textContent = 'Invalid Order ID format. Must be 15-22 digits.';
        err.style.display = 'block';
        return;
    }

    if (!licenseKey || !amount || amount <= 0) {
        err.textContent = 'Missing payment data. Please close and try again.';
        err.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="bnp-spinner"></span>Verifying...';

    var formData = new FormData();
    formData.append('action', 'verify_payment');
    formData.append('license_key', licenseKey);
    formData.append('order_id', oid);
    formData.append('amount', amount);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Verify payment';
        if (data.success) {
            ok.textContent = '✅ ' + data.message;
            ok.style.display = 'block';
            btn.style.display = 'none';
            document.getElementById('bnp-oid').disabled = true;
            // Optionally close modal after success
        } else {
            err.textContent = data.message || 'Verification failed. Please try again.';
            err.style.display = 'block';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = 'Verify payment';
        err.textContent = 'Network error. Please try again.';
        err.style.display = 'block';
    });
}

// ===== ENTER KEY SUPPORT =====
document.addEventListener('DOMContentLoaded', function() {
    var oidInput = document.getElementById('bnp-oid');
    if (oidInput) {
        oidInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                verifyPayment();
            }
        });
    }
    var amountInput = document.getElementById('amountInput');
    if (amountInput) {
        amountInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                startPayment();
            }
        });
    }
    var licenseInput = document.getElementById('licenseKey');
    if (licenseInput) {
        licenseInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                startPayment();
            }
        });
    }
});

// Additional CSS for toast animation
var style = document.createElement('style');
style.textContent = '@keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }';
document.head.appendChild(style);
</script>

</body>
</html>
