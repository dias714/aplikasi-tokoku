document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // MOBILE DRAWER NAVIGATION (NEW)
    // ========================================
    const btnToggle = document.getElementById('btnToggleMobileMenu');
    const btnClose = document.getElementById('btnCloseMobileDrawer');
    const drawerOverlay = document.getElementById('mobileDrawerOverlay');
    const drawerMenu = document.getElementById('mobileDrawerMenu');

    function openMobileDrawer() {
        if (drawerOverlay && drawerMenu) {
            drawerOverlay.classList.add('is-active');
            drawerMenu.classList.add('is-active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeMobileDrawer() {
        if (drawerOverlay && drawerMenu) {
            drawerOverlay.classList.remove('is-active');
            drawerMenu.classList.remove('is-active');
            document.body.style.overflow = '';
        }
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', openMobileDrawer);
    }
    if (btnClose) {
        btnClose.addEventListener('click', closeMobileDrawer);
    }
    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', closeMobileDrawer);
    }

    // ========================================
    // KEYBOARD SHORTCUT ( '/' FOCUSES SEARCH )
    // ========================================
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMobileDrawer();
        }
        if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            const searchInput = document.querySelector('.store-header-search input') || document.getElementById('filterSearchInput');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    // ========================================
    // LEGACY MOBILE NAV ACCORDIONS
    // ========================================
    document.querySelectorAll('.mobile-page-nav summary').forEach(function (summary) {
        summary.addEventListener('click', function (event) {
            const navigation = summary.closest('.mobile-page-nav');
            if (navigation && navigation.open) {
                event.preventDefault();
                navigation.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('.mobile-page-nav-menu a').forEach(function (link) {
        link.addEventListener('click', function () {
            const navigation = link.closest('.mobile-page-nav');
            if (navigation) {
                navigation.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('.mobile-page-nav-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function () {
            const navigation = backdrop.closest('.mobile-page-nav');
            if (navigation) {
                navigation.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('.mobile-page-nav-close').forEach(function (button) {
        button.addEventListener('click', function () {
            const navigation = button.closest('.mobile-page-nav');
            if (navigation) {
                navigation.removeAttribute('open');
                const summary = navigation.querySelector('summary');
                if (summary) {
                    summary.focus();
                }
            }
        });
    });

    // ========================================
    // SHOW / HIDE PASSWORD
    // ========================================
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    if (passwordInput && togglePassword) {
        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            togglePassword.textContent = isPassword ? 'Sembunyikan' : 'Tampilkan';
            togglePassword.setAttribute('aria-label', isPassword ? 'Sembunyikan password' : 'Tampilkan password');
            togglePassword.setAttribute('aria-pressed', String(isPassword));
        });
    }

    // ========================================
    // LOGIN LOADING
    // ========================================
    const loginForm = document.getElementById('loginForm');
    const loginButton = document.getElementById('loginButton');

    if (loginForm && loginButton) {
        loginForm.addEventListener('submit', function (event) {
            if (!loginForm.checkValidity()) {
                return;
            }
            loginButton.disabled = true;
            loginButton.classList.add('is-loading');
        });
    }

    // ========================================
    // SALES REPORT PERIOD
    // ========================================
    const reportForm = document.querySelector('.report-filter-form');
    const reportPeriod = document.getElementById('report-period');
    const reportValue = document.getElementById('report-value');

    if (reportForm && reportPeriod && reportValue) {
        const periodInputs = reportForm.querySelectorAll('.report-period-input');

        const updatePeriodInput = function () {
            let activeInput = null;
            periodInputs.forEach(function (input) {
                const isActive = input.dataset.period === reportPeriod.value;
                input.disabled = !isActive;
                input.required = isActive;
                if (isActive) {
                    input.name = 'value';
                    activeInput = input;
                } else {
                    input.removeAttribute('name');
                }
            });
            reportValue.disabled = Boolean(activeInput);
        };

        reportPeriod.addEventListener('change', updatePeriodInput);
        updatePeriodInput();
    }

    // ========================================
    // CASHIER LIVE TOTAL AND CHANGE + QRIS
    // ========================================
    const cashierForm = document.querySelector('.cashier-checkout-form');
    if (cashierForm) {
        const quantityInputs = cashierForm.querySelectorAll('.cashier-quantity-input');
        const receivedInput = cashierForm.querySelector('#uang_diterima');
        const itemCount = cashierForm.querySelector('[data-cashier-items]');
        const totalOutput = cashierForm.querySelector('[data-cashier-total]');
        const receivedOutput = cashierForm.querySelector('[data-cashier-received]');
        const changeOutput = cashierForm.querySelector('[data-cashier-change]');
        const messageOutput = cashierForm.querySelector('[data-cashier-message]');
        const submitButton = cashierForm.querySelector('.cashier-submit-button');
        const cashFields = cashierForm.querySelector('[data-cash-fields]');
        const qrisPanel = cashierForm.querySelector('[data-qris-panel]');
        const qrisAmountLabel = cashierForm.querySelector('[data-qris-amount]');
        const qrisBox = document.getElementById('qris-qrcode');
        const methodRadios = cashierForm.querySelectorAll('input[name="metode_pembayaran"]');
        const methodLabels = cashierForm.querySelectorAll('[data-pay-method-label]');
        const currencyFormatter = new Intl.NumberFormat('id-ID', {
            maximumFractionDigits: 0
        });

        let lastTotalCents = 0;
        let lastQrisPayload = '';

        const formatRupiah = function (cents) {
            return 'Rp ' + currencyFormatter.format(Math.floor(cents / 100));
        };

        const getPaymentMethod = function () {
            const checked = cashierForm.querySelector('input[name="metode_pembayaran"]:checked');
            return checked ? checked.value : 'Tunai';
        };

        const renderQrisQr = function (totalCents) {
            if (!qrisBox) {
                return;
            }
            const amount = Math.floor(totalCents / 100);
            const payload = 'QRIS|TOKOKU|AMOUNT:' + amount + '|TS:' + Date.now();
            if (payload === lastQrisPayload && qrisBox.childNodes.length) {
                return;
            }
            lastQrisPayload = payload;
            qrisBox.innerHTML = '';
            if (totalCents <= 0) {
                qrisBox.innerHTML = '<span style="font-size:12px;color:#94A3B8;">Pilih produk dulu</span>';
                return;
            }
            if (typeof QRCode === 'function') {
                try {
                    new QRCode(qrisBox, {
                        text: payload,
                        width: 160,
                        height: 160,
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } catch (e) {
                    qrisBox.textContent = payload;
                }
            } else {
                // Fallback: API QR jika library belum load
                const img = document.createElement('img');
                img.alt = 'QRIS';
                img.width = 160;
                img.height = 160;
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' + encodeURIComponent(payload);
                qrisBox.appendChild(img);
            }
            if (qrisAmountLabel) {
                qrisAmountLabel.textContent = 'Total: ' + formatRupiah(totalCents);
            }
        };

        const updateMethodUi = function () {
            const method = getPaymentMethod();
            const isQris = method === 'QRIS';

            methodLabels.forEach(function (label) {
                const active = label.getAttribute('data-pay-method-label') === method;
                label.style.borderColor = active ? '#059669' : '#E2E8F0';
                label.style.background = active ? '#ECFDF5' : '#fff';
            });

            if (cashFields) {
                cashFields.style.display = isQris ? 'none' : 'block';
            }
            if (qrisPanel) {
                qrisPanel.style.display = isQris ? 'block' : 'none';
            }
            if (receivedInput) {
                if (isQris) {
                    receivedInput.removeAttribute('required');
                    receivedInput.value = String(Math.floor(lastTotalCents / 100));
                } else {
                    receivedInput.setAttribute('required', 'required');
                }
            }
            if (isQris) {
                renderQrisQr(lastTotalCents);
            }
        };

        const updateCashierTotals = function () {
            let totalCents = 0;
            let items = 0;

            quantityInputs.forEach(function (input) {
                const quantity = Math.max(0, Number.parseInt(input.value, 10) || 0);
                const priceCents = Number.parseInt(input.dataset.priceCents, 10) || 0;
                const subtotalCents = priceCents * quantity;
                const subtotalOutput = document.getElementById(input.dataset.subtotalTarget);

                totalCents += subtotalCents;
                items += quantity;
                if (subtotalOutput) {
                    subtotalOutput.dataset.cents = String(subtotalCents);
                    subtotalOutput.textContent = formatRupiah(subtotalCents);
                }
            });

            lastTotalCents = totalCents;
            const method = getPaymentMethod();
            const isQris = method === 'QRIS';

            let receivedCents = 0;
            if (isQris) {
                receivedCents = totalCents;
                if (receivedInput) {
                    receivedInput.value = totalCents > 0 ? String(Math.floor(totalCents / 100)) : '';
                }
            } else {
                const receivedValue = receivedInput ? receivedInput.value.trim() : '';
                receivedCents = receivedValue !== '' && /^\d+(?:\.\d{1,2})?$/.test(receivedValue)
                    ? Math.round(Number(receivedValue) * 100)
                    : 0;
            }

            const changeCents = Math.max(0, receivedCents - totalCents);

            if (itemCount) itemCount.textContent = currencyFormatter.format(items);
            if (totalOutput) totalOutput.textContent = formatRupiah(totalCents);
            if (receivedOutput) receivedOutput.textContent = formatRupiah(receivedCents);
            if (changeOutput) changeOutput.textContent = formatRupiah(changeCents);

            let paymentSufficient = false;
            if (totalCents === 0) {
                if (messageOutput) messageOutput.textContent = 'Pilih produk untuk menghitung total.';
            } else if (isQris) {
                paymentSufficient = true;
                if (messageOutput) messageOutput.textContent = 'QRIS siap. Tunjukkan QR ke pelanggan, lalu simpan transaksi.';
                renderQrisQr(totalCents);
            } else if (receivedCents < totalCents) {
                if (messageOutput) messageOutput.textContent = 'Uang diterima masih kurang ' + formatRupiah(totalCents - receivedCents) + '.';
            } else {
                paymentSufficient = true;
                if (messageOutput) messageOutput.textContent = 'Pembayaran cukup. Kembalian ' + formatRupiah(changeCents) + '.';
            }

            if (messageOutput) {
                messageOutput.classList.toggle('is-error', totalCents > 0 && !isQris && receivedCents < totalCents);
                messageOutput.classList.toggle('is-success', paymentSufficient);
            }
            if (submitButton) {
                submitButton.disabled = !paymentSufficient;
            }
        };

        quantityInputs.forEach(function (input) {
            input.addEventListener('input', updateCashierTotals);
            input.addEventListener('change', updateCashierTotals);
        });
        if (receivedInput) {
            receivedInput.addEventListener('input', updateCashierTotals);
        }
        methodRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                updateMethodUi();
                updateCashierTotals();
            });
        });
        updateMethodUi();
        updateCashierTotals();
    }

    // ========================================
    // THERMAL RECEIPT PRINT (popup + tinggi pas)
    // ========================================
    const receiptPage = document.querySelector('.receipt-page[data-printable="true"]');
    const printReceiptButton = document.querySelector('[data-print-receipt]');

    if (receiptPage && printReceiptButton) {
        printReceiptButton.addEventListener('click', function () {
            const receipt = receiptPage.querySelector('.cashier-receipt');
            if (!receipt) {
                return;
            }

            const receiptHtml = receipt.outerHTML;
            const printWindow = window.open('', '_blank', 'width=280,height=700');
            if (!printWindow) {
                alert('Izinkan pop-up browser untuk mencetak nota.');
                return;
            }

            printWindow.document.write(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Nota</title>' +
                '<style id="base-style">' +
                '*{margin:0;padding:0;box-sizing:border-box}' +
                'html,body{margin:0;padding:0;background:#fff}' +
                'body{font-family:"Courier New",Courier,monospace;font-size:10.5px;line-height:1.28;color:#111}' +
                '.cashier-receipt{width:54mm;margin:0;padding:2mm 1.5mm;border:none}' +
                '.receipt-header{text-align:center;margin-bottom:4px}' +
                '.receipt-header h1{margin:0;font-size:13px;font-weight:800;letter-spacing:1px}' +
                '.receipt-header p{margin:1px 0 0;font-size:9.5px}' +
                '.receipt-rule{border:none;border-top:1px dashed #333;margin:4px 0}' +
                '.receipt-meta{font-size:9.5px}' +
                '.receipt-meta div{display:flex;justify-content:space-between;gap:4px;margin-bottom:1px}' +
                '.receipt-items{width:100%;border-collapse:collapse;font-size:9.5px;table-layout:fixed}' +
                '.receipt-items th,.receipt-items td{padding:1px 0;vertical-align:top;text-align:left;word-break:break-word}' +
                '.receipt-items th{border-bottom:1px solid #333;font-weight:700}' +
                '.receipt-items .col-qty{width:11%;text-align:center}' +
                '.receipt-items .col-price,.receipt-items .col-sub{width:22%;text-align:right}' +
                '.receipt-item-name{display:block;word-break:break-word}' +
                '.receipt-totals{font-size:10.5px}' +
                '.receipt-totals div{display:flex;justify-content:space-between;margin-bottom:1px}' +
                '.receipt-totals .receipt-change{font-weight:800;font-size:11.5px;margin-top:3px}' +
                '.receipt-footer{text-align:center;font-size:9.5px;margin-top:3px}' +
                '.receipt-footer strong{display:block;margin-bottom:1px}' +
                '.receipt-barcode-center{text-align:center;margin-top:5px}' +
                '.receipt-barcode-center svg{max-width:100%;height:28px;display:block;margin:0 auto}' +
                '.receipt-barcode-center .code-text{font-size:8.5px;letter-spacing:0.4px;margin-top:1px}' +
                '</style></head><body>' +
                receiptHtml +
                '<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>' +
                '<script>' +
                'function doPrint(){' +
                '  var el=document.querySelector(".cashier-receipt");' +
                '  if(!el){window.print();return;}' +
                '  var hPx=Math.max(el.scrollHeight,el.offsetHeight,el.getBoundingClientRect().height);' +
                '  var hMm=Math.ceil(hPx*25.4/96)+3;' +
                '  if(hMm<35)hMm=35;' +
                '  if(hMm>180)hMm=180;' +
                '  var s=document.createElement("style");' +
                '  s.textContent="@page{size:58mm "+hMm+"mm;margin:0!important}"+' +
                '    "html,body{width:58mm!important;height:"+hMm+"mm!important;margin:0!important;padding:0!important}"+' +
                '    ".cashier-receipt{width:54mm!important;margin:0!important}";' +
                '  document.head.appendChild(s);' +
                '  setTimeout(function(){window.print();},50);' +
                '}' +
                'window.onload=function(){' +
                '  var svg=document.getElementById("receipt-trx-barcode");' +
                '  if(svg&&typeof JsBarcode==="function"){' +
                '    try{JsBarcode(svg,svg.getAttribute("data-barcode-value")||"",{format:"CODE128",width:1.15,height:28,displayValue:false,margin:0,background:"transparent"});}' +
                '    catch(e){}' +
                '  }' +
                '  setTimeout(doPrint,150);' +
                '};' +
                'window.onafterprint=function(){window.close();};' +
                '<\/script></body></html>'
            );
            printWindow.document.close();
        });
    }

    // ========================================
    // PRODUCT IMAGE PREVIEW
    // ========================================
    document.querySelectorAll('.product-image-editor').forEach(function (editor) {
        const fileInput = editor.querySelector('.product-image-input');
        const preview = editor.querySelector('.product-image-preview');
        const removeButton = editor.querySelector('.product-image-remove');
        const removeInput = editor.querySelector('[name="hapus_gambar"]');
        const fileName = editor.querySelector('.product-image-filename');
        const selectLabel = editor.querySelector('.product-image-select');

        if (!fileInput || !preview || !removeButton || !removeInput || !fileName) {
            return;
        }

        let previewUrl = null;
        const defaultSrc = editor.dataset.defaultSrc;
        const hasCurrentImage = editor.dataset.currentImage === '1';

        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                fileInput.value = '';
                fileName.textContent = 'Ukuran gambar melebihi 2 MB.';
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                fileInput.value = '';
                fileName.textContent = 'Pilih file JPG, JPEG, PNG, atau WEBP.';
                return;
            }

            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            fileName.textContent = file.name;
            removeInput.value = '0';
            removeButton.hidden = !hasCurrentImage;
            if (selectLabel) {
                selectLabel.textContent = 'Ganti Gambar';
            }
        });

        removeButton.addEventListener('click', function () {
            fileInput.value = '';
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            preview.src = defaultSrc;
            fileName.textContent = 'Gambar dihapus · JPG, JPEG, PNG, WEBP · Maksimal 2 MB';
            removeInput.value = hasCurrentImage ? '1' : '0';
            removeButton.hidden = true;
            if (selectLabel) {
                selectLabel.textContent = 'Pilih Gambar';
            }
        });
    });


    // ========================================
    // KASIR DASHBOARD HASH SCROLL (Transaksi / Stok)
    // ========================================
    (function () {
        function scrollToKasirHash() {
            const hash = window.location.hash;
            if (!hash || (hash !== '#transaksi-kasir' && hash !== '#stok-kasir')) {
                return;
            }
            const target = document.querySelector(hash);
            if (!target) {
                return;
            }
            // Clear previous highlight
            document.querySelectorAll('.is-hash-target').forEach(function (el) {
                el.classList.remove('is-hash-target');
            });
            target.classList.add('is-hash-target');
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Remove highlight after a moment
            setTimeout(function () {
                target.classList.remove('is-hash-target');
            }, 1800);
        }

        // On full page load with hash
        if (window.location.hash === '#transaksi-kasir' || window.location.hash === '#stok-kasir') {
            // slight delay so layout is ready
            setTimeout(scrollToKasirHash, 80);
        }

        // When clicking sidebar links that point to the same page + hash
        document.querySelectorAll('a[href*="#transaksi-kasir"], a[href*="#stok-kasir"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                const href = link.getAttribute('href') || '';
                const isSamePage = href.indexOf('dashboard.php') !== -1 && window.location.pathname.indexOf('dashboard.php') !== -1;
                if (isSamePage) {
                    e.preventDefault();
                    const hashPart = href.includes('#transaksi-kasir') ? '#transaksi-kasir' : '#stok-kasir';
                    if (history.pushState) {
                        history.pushState(null, '', hashPart);
                    } else {
                        window.location.hash = hashPart;
                    }
                    scrollToKasirHash();
                }
            });
        });
    })();

});
