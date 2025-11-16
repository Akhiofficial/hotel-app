// CRITICAL: Define this function FIRST before anything else
window.openRoomModalFromCard = window.openRoomModalFromCard || function(cardElement) {
    console.log('openRoomModalFromCard called', cardElement);
    
    const bookBtn = cardElement.querySelector('.bookBtn');
    if (bookBtn && bookBtn.dataset.room) {
        try {
            const room = JSON.parse(bookBtn.dataset.room);
            if (window.openRoomModal) {
                window.openRoomModal(room);
            } else {
                setTimeout(function() {
                    if (window.openRoomModal) {
                        window.openRoomModal(room);
                    }
                }, 100);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
};

// Define modal functions globally so they're available immediately
window.openRoomModal = function(room) {
    console.log('openRoomModal called with:', room);
    
    const modal = document.getElementById('roomDetailsModal');
    if (!modal) {
        console.error('Modal element not found!');
        // Try again after a short delay
        setTimeout(function() {
            const modalRetry = document.getElementById('roomDetailsModal');
            if (modalRetry && room) {
                window.openRoomModal(room);
            }
        }, 100);
        return;
    }
    
    if (!room) {
        console.error('Room data not provided');
        return;
    }
    
    console.log('Opening modal for room:', room);
    
    // Set room data
    const titleEl = document.getElementById('modalRoomTitle');
    const priceEl = document.getElementById('modalRoomPrice');
    const codeEl = document.getElementById('modalRoomCode');
    const capacityEl = document.getElementById('modalRoomCapacity');
    const quantityEl = document.getElementById('modalRoomQuantity');
    const descEl = document.getElementById('modalRoomDescription');
    
    if (titleEl) titleEl.textContent = room.title || 'Room';
    if (priceEl) priceEl.textContent = '₹' + parseFloat(room.price || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' /night';
    if (codeEl) codeEl.textContent = room.code || 'N/A';
    if (capacityEl) capacityEl.textContent = room.capacity || 1;
    if (quantityEl) quantityEl.textContent = room.quantity || 1;
    if (descEl) descEl.textContent = room.description || 'Comfortable and well-appointed room with modern amenities.';
    
    // Set image
    const img = document.getElementById('modalRoomImage');
    if (img) {
        if (room.image) {
            // Handle image path - check if it already has uploads/ or http
            let imagePath = room.image;
            
            // Remove duplicate uploads/ if present
            imagePath = imagePath.replace(/^uploads\/uploads\//, 'uploads/');
            
            // If it doesn't start with uploads/ or http, add uploads/
            if (imagePath.indexOf('uploads/') !== 0 && imagePath.indexOf('http') !== 0 && imagePath.indexOf('https') !== 0) {
                imagePath = 'uploads/' + imagePath;
            }
            
            console.log('Setting image path to:', imagePath); // Debug
            img.src = imagePath;
            img.alt = room.title || 'Room Image';
            img.onerror = function() {
                console.log('Image failed to load, using placeholder');
                this.src = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=Room+Image';
            };
        } else {
            img.src = 'https://via.placeholder.com/600x400/20B2AA/ffffff?text=Room+Image';
            img.alt = 'Room Image';
        }
    }
    
    // Set book button data
    const bookBtn = document.getElementById('modalBookBtn');
    if (bookBtn) {
        bookBtn.setAttribute('data-room', JSON.stringify(room));
        bookBtn.onclick = function(e) {
            e.stopPropagation();
            window.closeRoomModal();
            // Trigger booking modal by finding the original book button
            const originalBookBtn = document.querySelector(`.bookBtn[data-room*='"id":${room.id}']`);
            if (originalBookBtn) {
                originalBookBtn.click();
            } else {
                // Fallback: manually open booking modal
                const bookingModal = document.getElementById('bookingModal');
                if (bookingModal) {
                    bookingModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                    const roomIdInput = document.getElementById('room_id');
                    if (roomIdInput) roomIdInput.value = room.id;
                }
            }
        };
    }
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    console.log('Modal should be visible now');
};

window.closeRoomModal = function() {
    const modal = document.getElementById('roomDetailsModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
};

// Global function to open room modal from card click
window.openRoomModalFromCard = function(cardElement) {
    console.log('openRoomModalFromCard called', cardElement);
    
    // Get room data from the book button inside the card
    const bookBtn = cardElement.querySelector('.bookBtn');
    if (bookBtn && bookBtn.dataset.room) {
        try {
            const room = JSON.parse(bookBtn.dataset.room);
            console.log('Room data:', room);
            window.openRoomModal(room);
        } catch (error) {
            console.error('Error parsing room data:', error);
            alert('Error loading room details. Please try again.');
        }
    } else {
        console.error('Book button or room data not found in card');
    }
};

// Carousel functionality
document.addEventListener('DOMContentLoaded', function() {
    // Property carousel
    const carousels = document.querySelectorAll('.properties-carousel, .testimonial-carousel');
    
    carousels.forEach(carousel => {
        const prevBtn = carousel.querySelector('.prev');
        const nextBtn = carousel.querySelector('.next');
        const items = carousel.querySelectorAll('.property-card, .testimonial-card');
        let currentIndex = 0;
        
        if (items.length > 0) {
            function showItems() {
                items.forEach((item, index) => {
                    item.style.display = 'none';
                });
                
                // Show 4 items at a time for properties, 1 for testimonials
                const itemsToShow = carousel.classList.contains('testimonial-carousel') ? 1 : 4;
                
                for (let i = 0; i < itemsToShow; i++) {
                    const index = (currentIndex + i) % items.length;
                    if (items[index]) {
                        items[index].style.display = 'block';
                    }
                }
            }
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    currentIndex = (currentIndex - 1 + items.length) % items.length;
                    showItems();
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    currentIndex = (currentIndex + 1) % items.length;
                    showItems();
                });
            }
            
            showItems();
        }
    });
    
    // Hotel Reservation Search Functionality
    const btnSearch = document.getElementById('btnSearch');
    const checkinInput = document.getElementById('search_checkin');
    const checkoutInput = document.getElementById('search_checkout');
    
    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    if (checkinInput) {
        checkinInput.setAttribute('min', today);
        checkinInput.addEventListener('change', function() {
            if (checkoutInput && this.value) {
                const checkinDate = new Date(this.value);
                checkinDate.setDate(checkinDate.getDate() + 1);
                checkoutInput.setAttribute('min', checkinDate.toISOString().split('T')[0]);
            }
        });
    }
    
    if (checkoutInput) {
        checkoutInput.setAttribute('min', today);
    }
    
    if (btnSearch) {
        btnSearch.addEventListener('click', function(e) {
            e.preventDefault();
            performRoomSearch();
        });
    }
    
    function performRoomSearch() {
        const checkIn = checkinInput?.value || '';
        const checkOut = checkoutInput?.value || '';
        const guests = document.getElementById('search_guests')?.value || '2';
        const roomType = document.getElementById('search_room_type')?.value || '';
        
        // Validate dates
        if (!checkIn || !checkOut) {
            alert('Please select both check-in and check-out dates');
            return;
        }
        
        if (new Date(checkOut) <= new Date(checkIn)) {
            alert('Check-out date must be after check-in date');
            return;
        }
        
        // Scroll to rooms section
        const roomsSection = document.getElementById('all-rooms');
        if (roomsSection) {
            roomsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Filter rooms based on search criteria
        filterRoomsBySearch({
            checkIn,
            checkOut,
            guests: parseInt(guests),
            roomType
        });
    }
    
    function filterRoomsBySearch(criteria) {
        const propertyCards = document.querySelectorAll('#roomsGrid .property-card');
        let visibleCount = 0;
        
        propertyCards.forEach(card => {
            const cardRoomType = card.getAttribute('data-room-type') || '';
            const capacityText = card.querySelector('.property-details')?.textContent || '';
            const capacityMatch = capacityText.match(/Capacity:\s*(\d+)/);
            const capacity = capacityMatch ? parseInt(capacityMatch[1]) : 0;
            
            let shouldShow = true;
            
            // Filter by room type
            if (criteria.roomType && cardRoomType !== criteria.roomType) {
                shouldShow = false;
            }
            
            // Filter by capacity (guests)
            if (capacity < criteria.guests) {
                shouldShow = false;
            }
            
            // Show/hide card
            if (shouldShow) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        // Show message if no results
        const roomsGrid = document.getElementById('roomsGrid');
        let noResultsMsg = document.getElementById('no-results-message');
        if (visibleCount === 0) {
            if (!noResultsMsg && roomsGrid) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.id = 'no-results-message';
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.innerHTML = `
                    <i class="fas fa-search" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                    <h3>No rooms available</h3>
                    <p>We couldn't find any rooms matching your criteria. Please try different dates or room type.</p>
                `;
                roomsGrid.appendChild(noResultsMsg);
            }
        } else {
            if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
    }
    
    // Room type filter buttons
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filterType = this.getAttribute('data-filter');
            const propertyCards = document.querySelectorAll('#roomsGrid .property-card');
            
            propertyCards.forEach(card => {
                if (filterType === 'all') {
                    card.style.display = 'block';
                } else {
                    const cardRoomType = card.getAttribute('data-room-type') || '';
                    if (cardRoomType === filterType) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });
    
    // Room type cards click handler
    const propertyTypeCards = document.querySelectorAll('.property-type-card');
    propertyTypeCards.forEach(card => {
        card.addEventListener('click', function() {
            propertyTypeCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            
            const roomType = this.getAttribute('data-room-type');
            if (roomType) {
                document.getElementById('search_room_type').value = roomType;
                // Scroll to rooms section
                const roomsSection = document.getElementById('all-rooms');
                if (roomsSection) {
                    roomsSection.scrollIntoView({ behavior: 'smooth' });
                }
                // Filter rooms
                filterRoomsBySearch({
                    checkIn: checkinInput?.value || '',
                    checkOut: checkoutInput?.value || '',
                    guests: parseInt(document.getElementById('search_guests')?.value || '2'),
                    roomType: roomType
                });
            }
        });
    });
    
    // Newsletter form
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            alert('Thank you for subscribing! We will send updates to ' + email);
            this.reset();
        });
    }
    
    // Video play button
    const videoPlaceholder = document.querySelector('.video-placeholder');
    if (videoPlaceholder) {
        videoPlaceholder.addEventListener('click', function() {
            alert('Video player would open here');
        });
    }

    // Room card click handler for modal - Make sure this runs after DOM is ready
    function setupRoomCardClicks() {
        const propertyCards = document.querySelectorAll('#roomsGrid .property-card');
        console.log('Found property cards:', propertyCards.length); // Debug
        
        propertyCards.forEach(card => {
            // Remove any existing listeners to avoid duplicates
            const newCard = card.cloneNode(true);
            card.parentNode.replaceChild(newCard, card);
            
            newCard.addEventListener('click', function(e) {
                console.log('Card clicked!'); // Debug
                
                // Don't open modal if clicking on the book button
                if (e.target.closest('.bookBtn')) {
                    console.log('Clicked on book button, skipping modal');
                    return;
                }
                
                // Get room data from the book button inside the card
                const bookBtn = this.querySelector('.bookBtn');
                console.log('Book button found:', bookBtn); // Debug
                
                if (bookBtn && bookBtn.dataset.room) {
                    try {
                        const room = JSON.parse(bookBtn.dataset.room);
                        console.log('Room data parsed:', room); // Debug
                        if (window.openRoomModal) {
                            window.openRoomModal(room);
                        } else {
                            console.error('openRoomModal function not found');
                        }
                    } catch (error) {
                        console.error('Error parsing room data:', error);
                        alert('Error loading room details. Please try again.');
                    }
                } else {
                    console.error('Book button or room data not found');
                }
            });
            
            // Add visual feedback
            newCard.style.cursor = 'pointer';
        });
    }
    
    // Setup room card clicks
    setupRoomCardClicks();
    
    // Also setup after a short delay in case cards are added dynamically
    setTimeout(setupRoomCardClicks, 500);
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.closeRoomModal();
        }
    });
});
