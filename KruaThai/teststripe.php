<?php
/**
 * Somdul Table - Stripe Payment Testing Interface
 * File: teststripe.php
 * Description: Simple UI for testing Stripe payments with database integration
 */

session_start();

// For testing, create a fake user session if none exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'test-user-' . uniqid();
    $_SESSION['user_email'] = 'test@somdultable.com';
    $_SESSION['user_name'] = 'Test User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Payment Test | Somdul Table</title>
    
    <!-- Stripe JavaScript -->
    <script src="https://js.stripe.com/v3/"></script>
    
    <style>
        /* BaticaSans Font */
        @font-face {
            font-family: 'BaticaSans';
            src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
                 url('./Font/BaticaSans-Regular.woff') format('woff'),
                 url('./Font/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        /* CSS Variables - Somdul Table Design System */
        :root {
            --brown: #bd9379;
            --white: #ffffff;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #d4c4b8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .logo {
            color: var(--brown);
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .subtitle {
            color: var(--text-gray);
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .test-info {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #fdd835;
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #856404;
        }

        .test-info h3 {
            color: var(--curry);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .test-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .payment-form {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            color: var(--brown);
            margin-bottom: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            background: var(--white);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        #card-element {
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            background: var(--white);
            transition: var(--transition);
        }

        #card-element.StripeElement--focus {
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .btn {
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 56px;
        }

        .btn:hover:not(:disabled) {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            opacity: 0.7;
        }

        .loading {
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: var(--white);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .message {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #b8dacc;
        }

        .message.error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #f1b0b7;
        }

        .status-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
        }

        .status-title {
            font-size: 1.3rem;
            color: var(--brown);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .status-info {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-md);
            font-family: 'BaticaSans', sans-serif;
        }

        .status-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(189, 147, 121, 0.1);
        }

        .status-row:last-child {
            border-bottom: none;
        }

        .status-label {
            color: var(--text-gray);
            font-weight: 500;
        }

        .status-value {
            color: var(--text-dark);
            font-weight: 600;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .test-cards {
                grid-template-columns: 1fr;
            }

            .payment-form, .status-section {
                padding: 1.5rem;
            }

            .logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">Somdul Table</div>
            <div class="subtitle">Stripe Payment Integration Test</div>
        </div>

        <!-- Test Card Information -->
        <div class="test-info">
            <h3>🧪 Test Mode - Use These Card Numbers:</h3>
            <div class="test-cards">
                <div><strong>Success:</strong> 4242 4242 4242 4242</div>
                <div><strong>Declined:</strong> 4000 0000 0000 0002</div>
                <div><strong>Auth Required:</strong> 4000 0025 0000 3155</div>
                <div><strong>Processing Error:</strong> 4000 0000 0000 0119</div>
            </div>
            <p style="margin-top: 1rem;">
                <strong>📝 For ALL test cards:</strong><br>
                • <strong>Expiry:</strong> Any future date (12/25, 01/26, etc.)<br>
                • <strong>CVC:</strong> Any 3 digits (123, 456, etc.)<br>
                • <strong>ZIP Code:</strong> Must be 5 digits (12345, 90210, etc.)
            </p>
        </div>

        <!-- Payment Form -->
        <div class="payment-form">
            <h2 class="form-title">💳 Test Payment</h2>
            
            <!-- Connection Test Button -->
            <div style="margin-bottom: 2rem; padding: 1rem; background: var(--cream); border-radius: var(--radius-md);">
                <h4 style="margin-bottom: 1rem; color: var(--brown);">🔧 Debug Tools</h4>
                <button type="button" id="test-connection" class="btn" style="margin-bottom: 1rem; background: var(--sage);">
                    Test AJAX Connection
                </button>
                <div id="connection-result" style="font-size: 0.9rem; font-family: monospace;"></div>
            </div>
            
            <form id="payment-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="amount">Amount ($)</label>
                        <input type="number" id="amount" class="form-input" step="0.01" min="0.01" value="29.99" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="currency">Currency</label>
                        <select id="currency" class="form-select">
                            <option value="usd" selected>USD - US Dollar</option>
                            <option value="thb">THB - Thai Baht</option>
                            <option value="eur">EUR - Euro</option>
                            <option value="gbp">GBP - British Pound</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <input type="text" id="description" class="form-input" value="Somdul Table Test Order" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="subscription_id">Subscription ID (Optional)</label>
                    <input type="text" id="subscription_id" class="form-input" placeholder="Leave empty for testing">
                </div>

                <div class="form-group">
                    <label class="form-label">Card Information</label>
                    <div id="card-element">
                        <!-- Stripe Elements will create form elements here -->
                    </div>
                    <div id="card-errors" role="alert"></div>
                </div>

                <button id="submit-button" class="btn">
                    <span class="btn-text">Pay Now</span>
                    <div class="loading">
                        <div class="spinner"></div>
                        <span>Processing...</span>
                    </div>
                </button>
            </form>

            <div id="payment-messages"></div>
        </div>

        <!-- Status Information -->
        <div class="status-section">
            <h3 class="status-title">📊 Current Session Status</h3>
            <div class="status-info">
                <div class="status-row">
                    <span class="status-label">User ID:</span>
                    <span class="status-value"><?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                </div>
                <div class="status-row">
                    <span class="status-label">User Email:</span>
                    <span class="status-value"><?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                </div>
                <div class="status-row">
                    <span class="status-label">Session Time:</span>
                    <span class="status-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Replace with your actual Stripe publishable key
        const stripe = Stripe('pk_test_51S5VLiFEd3iWM0Hv4JjeeanjqbAvhNQte3UIshlzcqtKmUazvXXr073YwiVdJd6SDs5oi1BGxgYqXnYnImz8PePF00hJO5wbJI'); // Replace with your actual test publishable key

        const elements = stripe.elements();
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#2c3e50',
                    fontFamily: 'BaticaSans, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif',
                    '::placeholder': {
                        color: '#7f8c8d',
                    },
                },
                invalid: {
                    color: '#721c24',
                    iconColor: '#721c24'
                }
            },
            hidePostalCode: false, // Set to true if you want to disable postal code
            iconStyle: 'default'
        });

        cardElement.mount('#card-element');

        // Handle real-time validation errors from the card Element
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.innerHTML = `<div class="message error" style="margin-top: 1rem;">${event.error.message}</div>`;
            } else {
                displayError.innerHTML = '';
            }
        });

        // Handle form submission
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const messagesDiv = document.getElementById('payment-messages');
        
        // Connection test functionality
        document.getElementById('test-connection').addEventListener('click', async function() {
            const resultDiv = document.getElementById('connection-result');
            resultDiv.innerHTML = '🔄 Testing connection...';
            
            try {
                const response = await fetch('ajax/test_connection.php');
                const text = await response.text();
                
                console.log('Connection test response:', text);
                
                if (response.ok) {
                    const data = JSON.parse(text);
                    resultDiv.innerHTML = `
                        <div style="color: green;">✅ Connection Success!</div>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div style="color: red;">❌ HTTP Error: ${response.status}</div>
                        <pre>${text}</pre>
                    `;
                }
            } catch (error) {
                console.error('Connection test error:', error);
                resultDiv.innerHTML = `
                    <div style="color: red;">❌ Connection Failed:</div>
                    <pre>${error.message}</pre>
                `;
            }
        });

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            setLoading(true);
            messagesDiv.innerHTML = '';

            // Get form data
            const formData = {
                amount: parseFloat(document.getElementById('amount').value),
                currency: document.getElementById('currency').value,
                description: document.getElementById('description').value,
                subscription_id: document.getElementById('subscription_id').value || null,
                user_id: '<?php echo $_SESSION['user_id']; ?>'
            };

            try {
                // Create payment intent
                const response = await fetch('ajax/create_payment_intent.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                // Debug: Check if response is OK
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP Error:', response.status, response.statusText);
                    console.error('Error Response:', errorText);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                // Debug: Get response text first to see what we're getting
                const responseText = await response.text();
                console.log('Raw response:', responseText);

                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('JSON Parse Error:', jsonError);
                    console.error('Response was:', responseText.substring(0, 200) + '...');
                    throw new Error(`Server returned invalid JSON. Response: ${responseText.substring(0, 100)}`);
                }

                if (!data.success) {
                    throw new Error(data.message || 'Payment intent creation failed');
                }

                // Confirm payment with Stripe
                const {error: stripeError, paymentIntent} = await stripe.confirmCardPayment(
                    data.payment_intent.client_secret,
                    {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                email: '<?php echo $_SESSION['user_email']; ?>',
                                name: '<?php echo $_SESSION['user_name']; ?>',
                                address: {
                                    line1: '123 Test Street',
                                    city: 'Test City',
                                    state: 'CA',
                                    postal_code: '12345',
                                    country: 'US'
                                }
                            }
                        }
                    }
                );

                if (stripeError) {
                    throw new Error(stripeError.message);
                }

                // Payment succeeded
                showMessage('success', `
                    <strong>🎉 Payment Successful!</strong><br>
                    Payment ID: ${paymentIntent.id}<br>
                    Transaction ID: ${data.payment_record.transaction_id}<br>
                    Amount: $${data.payment_record.amount} ${data.payment_record.currency}<br>
                    Status: ${paymentIntent.status}
                `);

                // Reset form
                form.reset();
                cardElement.clear();

                // TODO: You might want to call a webhook handler or update payment status

            } catch (error) {
                console.error('Payment error:', error);
                showMessage('error', `<strong>❌ Payment Failed:</strong> ${error.message}`);
            }

            setLoading(false);
        });

        function setLoading(loading) {
            submitButton.disabled = loading;
            const btnText = document.querySelector('.btn-text');
            const loadingEl = document.querySelector('.loading');
            
            if (loading) {
                btnText.style.display = 'none';
                loadingEl.style.display = 'flex';
            } else {
                btnText.style.display = 'inline';
                loadingEl.style.display = 'none';
            }
        }

        function showMessage(type, message) {
            messagesDiv.innerHTML = `<div class="message ${type}">${message}</div>`;
            messagesDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Format amount input
        document.getElementById('amount').addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            if (value < 0.01) {
                e.target.value = '0.01';
            }
        });
    </script>
</body>
</html>