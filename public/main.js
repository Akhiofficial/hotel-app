// Carousel functionality
document.addEventListener('DOMContentLoaded', function () {
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
        checkinInput.addEventListener('change', function () {
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
        btnSearch.addEventListener('click', function (e) {
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
            // Use case-insensitive comparison
            const normCardType = cardRoomType.trim().toLowerCase();
            const normCriteriaType = criteria.roomType.trim().toLowerCase();

            if (criteria.roomType && normCardType !== normCriteriaType) {
                shouldShow = false;
            }

            // Filter by capacity (guests) - only if no specific room type is selected
            // (If user selects a specific room type, we show it even if capacity doesn't match default guests)
            if (!criteria.roomType && capacity < criteria.guests) {
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



    // Room type cards click handler
    const propertyTypeCards = document.querySelectorAll('.property-type-card');
    propertyTypeCards.forEach(card => {
        card.addEventListener('click', function () {
            propertyTypeCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            const roomType = this.getAttribute('data-room-type');
            if (roomType) {
                document.getElementById('search_room_type').value = roomType;

                // Auto-set guests to 1 to ensure this room type shows up (in case it has capacity 1)
                const guestsInput = document.getElementById('search_guests');
                if (guestsInput) guestsInput.value = '1';

                // Scroll to rooms section
                const roomsSection = document.getElementById('all-rooms');
                if (roomsSection) {
                    roomsSection.scrollIntoView({ behavior: 'smooth' });
                }
                // Filter rooms
                filterRoomsBySearch({
                    checkIn: checkinInput?.value || '',
                    checkOut: checkoutInput?.value || '',
                    guests: 1,
                    roomType: roomType
                });
            }
        });
    });

    // Newsletter form
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;
            alert('Thank you for subscribing! We will send updates to ' + email);
            this.reset();
        });
    }

    // Video play button
    const videoPlaceholder = document.querySelector('.video-placeholder');
    if (videoPlaceholder) {
        videoPlaceholder.addEventListener('click', function () {
            alert('Video player would open here');
        });
    }
});
