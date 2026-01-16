<!-- booking_modal.php -->
<div id="bookingModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2 id="modalRoomTitle">Book Room</h2>
    <form id="bookingForm" action="../admin/api.php?action=create_booking" method="post" enctype="multipart/form-data">
      <input type="hidden" name="room_id" id="room_id">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="customer_name" required placeholder="Enter your full name">
      </div>
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="customer_email" required placeholder="your.email@example.com">
      </div>
      <div class="form-group">
        <label>Phone *</label>
        <div style="display: flex; gap: 10px;">
          <select id="country_code"
            style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background:white;">
            <option value="+91">+91</option>
            <option value="+1">+1</option>
            <option value="+44">+44</option>
            <option value="+971">+971</option>
          </select>
          <input type="tel" id="details_phone" placeholder="10-digit number" maxlength="10"
            oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="flex: 1;">
          <input type="hidden" name="customer_phone" id="full_phone">
        </div>
      </div>
      <div class="form-group">
        <label>Identity Card (Optional)</label>
        <input type="file" name="identity_card" accept="image/*,application/pdf">
        <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
          Upload Aadhaar, PAN, Passport, or any valid ID
        </small>
      </div>
      <div class="form-group">
        <label>Check-in Date *</label>
        <input type="date" name="checkin" required>
      </div>
      <div class="form-group">
        <label>Check-out Date *</label>
        <input type="date" name="checkout" required>
      </div>
      <div class="form-group">
        <label>Payment Method *</label>
        <select name="payment_method" id="payment_method" required>
          <option value="cash">Cash on Arrival</option>
          <option value="bank_transfer">Bank Transfer (upload proof)</option>
          <option value="online">Online Payment</option>
        </select>
      </div>
      <div id="bankProofRow" style="display:none;" class="form-group">
        <label>Upload Bank Transfer Proof *</label>
        <input type="file" name="bank_proof" accept="image/*,application/pdf">
        <small>Upload screenshot or PDF of bank transfer receipt</small>
      </div>
      <div class="form-actions">
        <button type="button" class="btn-cancel"
          onclick="document.getElementById('bookingModal').style.display='none'; document.body.style.overflow='auto';">Cancel</button>
        <button type="submit" class="btn-submit">Place Booking</button>
      </div>
    </form>
  </div>
</div>