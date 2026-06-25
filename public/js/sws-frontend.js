/**
 * SWS Members Club — Frontend JavaScript.
 *
 * Handles: Stripe Elements, booking form submission, cancel modal,
 * waitlist join, calendar dropdown, tab switching.
 */
(function () {
    'use strict';

    var stripe, cardElement;

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        initStripe();
        initBookingForm();
        initGuestToggle();
        initCancelButtons();
        initCalendarDropdowns();
        initTabs();
        initWaitlistButtons();
        initCalendarSubscribe();
    });

    // -------------------------------------------------------------------------
    // Stripe Elements
    // -------------------------------------------------------------------------

    function initStripe() {
        var cardContainer = document.getElementById('sws-card-element');
        if (!cardContainer || !window.Stripe || !swsData.stripeKey) return;

        stripe = Stripe(swsData.stripeKey);
        var elements = stripe.elements();
        cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#333',
                    '::placeholder': { color: '#999' },
                },
            },
        });
        cardElement.mount('#sws-card-element');

        cardElement.on('change', function (event) {
            var errorEl = document.getElementById('sws-card-errors');
            errorEl.textContent = event.error ? event.error.message : '';
        });
    }

    // -------------------------------------------------------------------------
    // Booking Form
    // -------------------------------------------------------------------------

    function initBookingForm() {
        var form = document.getElementById('sws-booking-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleBooking(form);
        });
    }

    function handleBooking(form) {
        var button = document.getElementById('sws-book-button');
        var messagesEl = document.getElementById('sws-booking-messages');
        var eventId = form.dataset.eventId;
        var needsPayment = form.dataset.needsPayment === '1';

        button.disabled = true;
        button.textContent = swsData.i18n.booking;
        messagesEl.innerHTML = '';

        var includeGuest = form.querySelector('#sws-include-guest');
        var guestChecked = includeGuest && includeGuest.checked;

        var quantity = guestChecked ? 2 : 1;

        if (needsPayment) {
            // Step 1: Create PaymentIntent.
            apiPost('payment-intent', {
                event_id: eventId,
                quantity: quantity,
            }).then(function (data) {
                if (data.free) {
                    // No payment needed after all (server override).
                    return completeBooking(eventId, '', guestChecked, form);
                }

                // Step 2: Confirm card payment with Stripe.
                return stripe.confirmCardPayment(data.client_secret, {
                    payment_method: { card: cardElement },
                }).then(function (result) {
                    if (result.error) {
                        throw new Error(result.error.message);
                    }
                    // Step 3: Create booking with confirmed payment.
                    return completeBooking(eventId, result.paymentIntent.id, guestChecked, form);
                });
            }).catch(function (err) {
                showMessage(messagesEl, err.message || swsData.i18n.error, 'error');
                button.disabled = false;
                button.textContent = swsData.i18n.bookNow;
            });
        } else {
            // Free or events-included: book directly.
            completeBooking(eventId, '', guestChecked, form).catch(function (err) {
                showMessage(messagesEl, err.message || swsData.i18n.error, 'error');
                button.disabled = false;
                button.textContent = swsData.i18n.bookNow;
            });
        }
    }

    function completeBooking(eventId, paymentIntentId, guestChecked, form) {
        var data = {
            event_id: eventId,
            payment_intent_id: paymentIntentId,
            include_guest: guestChecked,
        };

        if (guestChecked) {
            var nameInput = form.querySelector('#sws-guest-name');
            var emailInput = form.querySelector('#sws-guest-email');
            data.guest_name = nameInput ? nameInput.value : '';
            data.guest_email = emailInput ? emailInput.value : '';
        }

        return apiPost('bookings', data).then(function (result) {
            var messagesEl = document.getElementById('sws-booking-messages');
            showMessage(messagesEl, result.message, 'success');

            // Hide the form.
            form.style.display = 'none';
        });
    }

    // -------------------------------------------------------------------------
    // Guest +1 toggle
    // -------------------------------------------------------------------------

    function initGuestToggle() {
        var toggle = document.getElementById('sws-include-guest');
        var fields = document.getElementById('sws-guest-fields');
        if (!toggle || !fields) return;

        toggle.addEventListener('change', function () {
            fields.style.display = toggle.checked ? 'block' : 'none';
        });
    }

    // -------------------------------------------------------------------------
    // Cancel tickets
    // -------------------------------------------------------------------------

    function initCancelButtons() {
        document.querySelectorAll('.sws-ticket-card__cancel-button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showCancelModal(btn);
            });
        });
    }

    function showCancelModal(btn) {
        var bookingId  = btn.dataset.bookingId;
        var eventTitle = btn.dataset.eventTitle;
        var eventDate  = btn.dataset.eventDate;
        var amount     = parseFloat(btn.dataset.amount);
        var hasGuest   = btn.dataset.hasGuest === '1';

        var guestNote = hasGuest
            ? ' This will also cancel your guest’s ticket for this event.'
            : '';

        // Create modal.
        var overlay = document.createElement('div');
        overlay.className = 'sws-modal-overlay';
        overlay.innerHTML =
            '<div class="sws-modal">' +
                '<h3 class="sws-modal__title">' + escHtml(eventTitle) + '</h3>' +
                '<p class="sws-modal__text">' +
                    'Are you sure you want to cancel your ticket for <strong>' +
                    escHtml(eventTitle) + '</strong> on ' + escHtml(eventDate) + '?' +
                    (amount > 0 ? ' You will receive a full refund.' : '') +
                    escHtml(guestNote) +
                '</p>' +
                '<div class="sws-modal__actions">' +
                    '<button class="sws-modal__cancel" type="button">Keep Ticket</button>' +
                    '<button class="sws-modal__confirm" type="button">Cancel Ticket</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(overlay);

        overlay.querySelector('.sws-modal__cancel').addEventListener('click', function () {
            overlay.remove();
        });

        overlay.querySelector('.sws-modal__confirm').addEventListener('click', function () {
            var confirmBtn = this;
            confirmBtn.disabled = true;
            confirmBtn.textContent = swsData.i18n.cancelling;

            apiDelete('bookings/' + bookingId).then(function () {
                // Cancelling a main ticket also cancels its guest ticket — reload
                // so both cards and the count update correctly.
                if (hasGuest) {
                    window.location.reload();
                    return;
                }

                // Remove the ticket card from DOM.
                var card = document.querySelector('[data-booking-id="' + bookingId + '"]');
                if (card) card.remove();
                overlay.remove();

                // Update tab count.
                var countEl = document.querySelector('[data-tab="upcoming"] .sws-my-tickets__tab-count');
                if (countEl) {
                    var current = parseInt(countEl.textContent, 10);
                    countEl.textContent = Math.max(0, current - 1);
                }
            }).catch(function (err) {
                alert(err.message || swsData.i18n.error);
                confirmBtn.disabled = false;
                confirmBtn.textContent = swsData.i18n.cancelTicket;
            });
        });

        // Close on overlay click.
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.remove();
        });
    }

    // -------------------------------------------------------------------------
    // Calendar dropdown
    // -------------------------------------------------------------------------

    function initCalendarDropdowns() {
        document.querySelectorAll('.sws-ticket-card__calendar-button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var dropdown = btn.closest('.sws-ticket-card__calendar-dropdown');
                dropdown.classList.toggle('sws-open');
            });
        });

        // Close dropdowns on outside click.
        document.addEventListener('click', function () {
            document.querySelectorAll('.sws-ticket-card__calendar-dropdown.sws-open').forEach(function (d) {
                d.classList.remove('sws-open');
            });
        });
    }

    // -------------------------------------------------------------------------
    // Tabs (My Tickets)
    // -------------------------------------------------------------------------

    function initTabs() {
        document.querySelectorAll('.sws-my-tickets__tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var tabName = tab.dataset.tab;

                // Toggle active tab.
                document.querySelectorAll('.sws-my-tickets__tab').forEach(function (t) {
                    t.classList.remove('sws-my-tickets__tab--active');
                });
                tab.classList.add('sws-my-tickets__tab--active');

                // Toggle panels.
                document.querySelectorAll('.sws-my-tickets__panel').forEach(function (p) {
                    p.style.display = p.dataset.panel === tabName ? 'block' : 'none';
                    if (p.dataset.panel === tabName) {
                        p.classList.add('sws-my-tickets__panel--active');
                    } else {
                        p.classList.remove('sws-my-tickets__panel--active');
                    }
                });
            });
        });
    }

    // -------------------------------------------------------------------------
    // Waitlist buttons
    // -------------------------------------------------------------------------

    function initWaitlistButtons() {
        document.querySelectorAll('.sws-booking__waitlist-button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var eventId = btn.dataset.eventId;
                var messagesEl = document.getElementById('sws-waitlist-messages');
                btn.disabled = true;
                btn.textContent = swsData.i18n.booking;
                if (messagesEl) messagesEl.innerHTML = '';

                apiPost('waitlist/' + eventId, {}).then(function (result) {
                    // Hide the button, show success message in its place.
                    btn.style.display = 'none';
                    if (messagesEl) {
                        showMessage(messagesEl, result.message, 'success');
                    } else {
                        btn.style.display = '';
                        btn.disabled = true;
                        btn.textContent = result.message;
                    }
                }).catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = 'Join Waitlist';
                    if (messagesEl) {
                        showMessage(messagesEl, err.message || swsData.i18n.error, 'error');
                    } else {
                        alert(err.message || swsData.i18n.error);
                    }
                });
            });
        });
    }

    // -------------------------------------------------------------------------
    // Calendar subscribe: copy link to clipboard
    // -------------------------------------------------------------------------

    function initCalendarSubscribe() {
        document.querySelectorAll('.sws-calendar-subscribe__copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-clipboard') || '';
                if (!url) return;

                var original = btn.textContent;
                var done = function () {
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = original; }, 2000);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done).catch(function () {
                        fallbackCopy(url, done);
                    });
                } else {
                    fallbackCopy(url, done);
                }
            });
        });
    }

    function fallbackCopy(text, onDone) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        if (onDone) onDone();
    }

    // -------------------------------------------------------------------------
    // REST API helpers
    // -------------------------------------------------------------------------

    function apiPost(endpoint, data) {
        return fetch(swsData.restUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': swsData.nonce,
            },
            body: JSON.stringify(data),
        }).then(handleResponse);
    }

    function apiDelete(endpoint) {
        return fetch(swsData.restUrl + endpoint, {
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': swsData.nonce,
            },
        }).then(handleResponse);
    }

    function handleResponse(res) {
        return res.json().then(function (data) {
            if (!res.ok) {
                throw new Error(data.message || 'Request failed.');
            }
            return data;
        });
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function showMessage(container, text, type) {
        var variant = 'info';
        if (type === 'error') variant = 'warning';
        if (type === 'success') variant = 'success';

        container.innerHTML = '<div class="sws-booking__notice sws-booking__notice--' + variant +
            '"><p>' + escHtml(text) + '</p></div>';

        // Scroll the message into view so the user sees it.
        if (container.scrollIntoView) {
            try {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                container.scrollIntoView();
            }
        }
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
