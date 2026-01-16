// assets.js
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('bookingModal');
  const close = modal.querySelector('.close');
  const bookBtns = document.querySelectorAll('.bookBtn');
  const roomIdInput = document.getElementById('room_id');
  const modalTitle = document.getElementById('modalRoomTitle');
  const paymentSelect = document.getElementById('payment_method');
  const bankProofRow = document.getElementById('bankProofRow');
  const bookingForm = document.getElementById('bookingForm');

  bookBtns.forEach(b => {
    b.addEventListener('click', () => {
      const room = JSON.parse(b.dataset.room);
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      modalTitle.textContent = `Book: ${room.title} (${room.code}) - ₹${parseFloat(room.price).toFixed(2)}`;
      roomIdInput.value = room.id;

      // Set minimum dates
      const today = new Date().toISOString().split('T')[0];
      const checkinInput = document.querySelector('#bookingForm input[name="checkin"]');
      const checkoutInput = document.querySelector('#bookingForm input[name="checkout"]');

      if (checkinInput) {
        checkinInput.setAttribute('min', today);
        checkinInput.value = '';
      }
      if (checkoutInput) {
        checkoutInput.setAttribute('min', today);
        checkoutInput.value = '';
      }

      // Reset form
      if (bookingForm) {
        bookingForm.reset();
        roomIdInput.value = room.id;
      }
    });
  });

  close.addEventListener('click', () => {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  });

  paymentSelect.addEventListener('change', () => {
    bankProofRow.style.display = paymentSelect.value === 'bank_transfer' ? 'block' : 'none';
    const bankProofInput = bankProofRow.querySelector('input[type="file"]');
    if (bankProofInput) {
      bankProofInput.required = paymentSelect.value === 'bank_transfer';
    }
  });

  // Handle form submission with AJAX
  if (bookingForm) {
    bookingForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const submitBtn = this.querySelector('button[type="submit"]');

      // Phone Validation
      const countryCode = document.getElementById('country_code').value;
      const phonePart = document.getElementById('details_phone').value;

      if (!/^\d{10}$/.test(phonePart)) {
        showMessage('Please enter a valid 10-digit phone number.', 'error');
        return;
      }

      // Update hidden input
      document.getElementById('full_phone').value = countryCode + ' ' + phonePart;

      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = 'Processing...';

      // Create FormData
      const formData = new FormData(this);

      // Show loading message
      showMessage('Processing your booking...', 'info');

      // Submit via AJAX
      fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showMessage(data.message || 'Booking confirmed successfully! Booking ID: ' + (data.booking_id || ''), 'success');
            bookingForm.reset();
            setTimeout(() => {
              modal.style.display = 'none';
              document.body.style.overflow = 'auto';
              showMessage('', ''); // Clear message
            }, 3000);
          } else {
            showMessage(data.error || 'Booking failed. Please try again.', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showMessage('An error occurred. Please try again or contact support.', 'error');
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        });
    });
  }

  // Show success/error messages from URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('success') === '1') {
    const bookingId = urlParams.get('booking_id');
    showMessage('Booking confirmed successfully!' + (bookingId ? ' Booking ID: ' + bookingId : ''), 'success');
    // Clean URL
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  if (urlParams.get('error')) {
    showMessage(urlParams.get('error'), 'error');
    window.history.replaceState({}, document.title, window.location.pathname);
  }

  // Function to show messages
  function showMessage(message, type) {
    // Remove existing message
    let messageDiv = document.getElementById('bookingMessage');
    if (messageDiv) {
      messageDiv.remove();
    }

    if (!message) return;

    messageDiv = document.createElement('div');
    messageDiv.id = 'bookingMessage';
    messageDiv.className = 'booking-message ' + type;
    messageDiv.textContent = message;

    // Insert before form
    if (bookingForm) {
      bookingForm.parentNode.insertBefore(messageDiv, bookingForm);
    } else {
      document.body.appendChild(messageDiv);
    }

    // Auto remove after 5 seconds for success/info
    if (type === 'success' || type === 'info') {
      setTimeout(() => {
        if (messageDiv) messageDiv.remove();
      }, 5000);
    }
  }

  // Close modal on outside click
  window.onclick = function (e) {
    if (e.target === modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
    }
  }
  // Mobile Navigation Toggle
  const navToggle = document.getElementById('navToggle');
  const mainNav = document.getElementById('mainNav');
  const navOverlay = document.getElementById('navOverlay');

  if (navToggle && mainNav && navOverlay) {
    navToggle.addEventListener('click', () => {
      mainNav.classList.toggle('active');
      navOverlay.classList.toggle('active');
      document.body.style.overflow = mainNav.classList.contains('active') ? 'hidden' : '';
    });

    navOverlay.addEventListener('click', () => {
      mainNav.classList.remove('active');
      navOverlay.classList.remove('active');
      document.body.style.overflow = '';
    });

    // Close menu when clicking a link
    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mainNav.classList.remove('active');
        navOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    });
  }
});
