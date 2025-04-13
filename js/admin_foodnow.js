// js/admin_foodnow.js

$(document).ready(function() {

    // --- Element Selectors ---
    const modal = $('#add-item-modal');
    const modalOverlay = modal.find('.modal-overlay');
    const closeModalBtn = $('#close-modal-btn');
    const showAddModalBtn = $('#show-add-modal-btn');
    const addItemForm = $('#add-item-form');
    const modalTitle = $('#modal-title');
    const formActionInput = $('#form-action'); // Hidden input for action type
    const editItemIdInput = $('#edit-item-id'); // Hidden input for item ID during edit
    const currentImageFilenameInput = $('#current-image-filename'); // Hidden input for current image name during edit
    const modalSubmitButton = $('#modal-submit-button');
    const modalSubmitButtonText = $('#modal-submit-button-text'); // Span inside the submit button
    const currentImagePreview = $('#current-image-preview'); // Div to show current image preview
    const formMessageDiv = $('#add-item-form-message'); // Div for showing messages inside the modal

    // --- Modal Handling ---

    function openModal() {
        formMessageDiv.text('').hide(); // Clear previous messages inside modal
        modal.addClass('active');
        $('body').css('overflow', 'hidden'); // Prevent background scrolling when modal is open
    }

    function closeModal() {
        modal.removeClass('active');
        $('body').css('overflow', ''); // Restore background scrolling
        resetForm(); // Reset the form every time the modal is closed
    }

    // Function to reset the modal form to its default state (for adding)
    function resetForm() {
        addItemForm[0].reset(); // Native DOM form reset method
        modalTitle.text('Thêm Món ăn mới');
        modalSubmitButtonText.text('Thêm món ăn');
        modalSubmitButton.find('i').removeClass('fa-save').addClass('fa-plus'); // Set icon to 'plus'
        formActionInput.val('add'); // Set hidden action input to 'add'
        editItemIdInput.val('');    // Clear hidden edit ID input
        currentImagePreview.html(''); // Clear the current image preview area
        currentImageFilenameInput.val(''); // Clear hidden input for current image filename
        formMessageDiv.text('').removeClass('alert-success alert-danger').hide(); // Clear and hide modal messages
        // Optional: remove any validation error styling classes if added
        addItemForm.find('.is-invalid').removeClass('is-invalid');
    }

    // Event Listener: Show Add Modal Button
    showAddModalBtn.on('click', function() {
        resetForm(); // Ensure form is clean for adding a new item
        openModal();
    });

    // Event Listeners: Close Modal
    closeModalBtn.on('click', closeModal);
    modalOverlay.on('click', closeModal);
    // Optional: Close modal on pressing the Escape key
    $(document).on('keydown', function(event) {
        if (event.key === "Escape" && modal.hasClass('active')) {
            closeModal();
        }
    });

    // --- Edit Modal Population (Global Function) ---
    // This function is called directly from the 'onclick' attribute in the PHP table rows
    window.openEditModal = function(foodData) {
        resetForm(); // Start with a clean slate

        // Set modal state to 'edit'
        modalTitle.text('Chỉnh sửa Món ăn');
        modalSubmitButtonText.text('Cập nhật món ăn');
        modalSubmitButton.find('i').removeClass('fa-plus').addClass('fa-save'); // Set icon to 'save'
        formActionInput.val('edit'); // Set hidden action input to 'edit'
        editItemIdInput.val(foodData.id); // Set the hidden ID input

        // Populate form fields with data from the clicked row
        $('#item-name').val(foodData.name);
        $('#item-type').val(foodData.type);
        // Format price correctly for input (assuming integer VND)
        $('#item-price').val(parseInt(foodData.price, 10) || ''); // Handle potential null/NaN
         // Ensure rate is treated as a string for matching select option value
        $('#item-rating').val(foodData.rate.toString());
        $('#item-description').val(foodData.description); // Use .val() for textarea

        // Handle image preview and set the hidden input for the current image filename
        if (foodData.image && foodData.image !== '') {
            const imagePath = 'uploads/' + foodData.image; // Make sure 'uploads/' path is correct
            // Display current image preview and filename
            currentImagePreview.html(`Ảnh hiện tại: <img src="${imagePath}" alt="Ảnh hiện tại" style="max-height: 60px; vertical-align: middle; margin-left: 10px; border-radius: 4px; border: 1px solid #eee;"> (${foodData.image})`);
            currentImageFilenameInput.val(foodData.image); // Set hidden input value
        } else {
            currentImagePreview.html('<span style="color: #888;">Chưa có ảnh.</span>');
            currentImageFilenameInput.val(''); // Ensure hidden input is empty if no image
        }

        openModal(); // Open the modal with the populated data
    }

    // --- Sidebar Toggle Logic ---
    const sidebar = $('.sidebar');
    const mainContent = $('.main-content');
    const headerToggle = $('.header-menu-toggle'); // Default toggle button in header
    let mobileToggleCreated = false; // Flag to track if mobile toggle exists

    // Function to toggle sidebar visibility (especially for mobile)
    function toggleSidebar() {
        sidebar.toggleClass('open');
        // Optional: Adjust main content margin if sidebar overlays content on mobile
        // Check your CSS for how the 'open' class affects layout
    }

    // Event Listener: Toggle using header button (visible on larger screens)
    headerToggle.on('click', toggleSidebar);

    // Function to dynamically create/remove a mobile-specific menu toggle button
    function manageMobileToggle() {
        const windowWidth = $(window).width();

        if (windowWidth <= 768) { // Mobile breakpoint (adjust if needed)
            if (!mobileToggleCreated) {
                // Create and add mobile toggle button if it doesn't exist
                const mobileToggle = $('<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>');
                $('.header-title').prepend(mobileToggle); // Add to the start of the header title div
                mobileToggle.on('click', toggleSidebar); // Add click listener
                mobileToggleCreated = true;
                headerToggle.hide(); // Hide the default toggle on mobile
            }
        } else { // Desktop view
            if (mobileToggleCreated) {
                // Remove mobile toggle if it exists
                $('.mobile-menu-toggle').remove();
                mobileToggleCreated = false;
                 headerToggle.show(); // Show the default toggle again
            }
            // Ensure sidebar isn't stuck in 'open' state when resizing to desktop
            sidebar.removeClass('open');
        }
    }

    // Initial check on page load and add resize listener
    manageMobileToggle();
    $(window).on('resize', manageMobileToggle);

    // --- Basic Client-Side Form Validation Example ---
    addItemForm.on('submit', function(e) {
        formMessageDiv.text('').hide(); // Clear previous modal messages
        let isValid = true;
        let message = '';
        let errorField = null;

        // Clear previous errors
        $(this).find('.is-invalid').removeClass('is-invalid');

        // Check Name
        if ($('#item-name').val().trim() === '') {
            message = 'Tên món ăn không được để trống.';
            errorField = $('#item-name');
            isValid = false;
        }
        // Check Type
        else if ($('#item-type').val() === null || $('#item-type').val() === '') {
             message = 'Vui lòng chọn loại món ăn.';
             errorField = $('#item-type');
             isValid = false;
        }
        // Check Price
        else if ($('#item-price').val().trim() === '' || isNaN(parseFloat(preg_replace('/[^\d]/', '', $('#item-price').val()))) || parseFloat(preg_replace('/[^\d]/', '', $('#item-price').val())) < 0) {
            message = 'Giá không hợp lệ (phải là số không âm).';
            errorField = $('#item-price');
            isValid = false;
        }
        // Check Rating
        else if ($('#item-rating').val() === null || $('#item-rating').val() === '') {
             message = 'Vui lòng chọn đánh giá.';
             errorField = $('#item-rating');
             isValid = false;
        }
        // Optional: Check Image (only require image for 'add' maybe?)
        /*
        if ($('#form-action').val() === 'add' && $('#item-image').val() === '') {
             message = 'Vui lòng chọn ảnh món ăn khi thêm mới.';
             errorField = $('#item-image');
             isValid = false;
        }
        */

        if (!isValid) {
            e.preventDefault(); // Prevent default form submission
            formMessageDiv.text(message).addClass('alert-danger').show(); // Show error message in modal
            if(errorField) {
                errorField.addClass('is-invalid').focus(); // Highlight the field and focus
            }
        } else {
             // Optional: Show a loading indicator on the submit button
             modalSubmitButton.prop('disabled', true).find('span').text('Đang xử lý...');
             // Form will submit normally if validation passes
        }
    });

    // Function to remove non-digit characters (useful for price input)
    function preg_replace(pattern, replacement, subject) {
        // Basic JS equivalent for simple cases
        if (pattern === '/[^\\d]/') {
            return subject.replace(/\D/g, replacement);
        }
        return subject; // Return original if pattern not matched
    }

    // Automatically format price input (remove non-digits) on input change
    $('#item-price').on('input', function() {
        var value = $(this).val();
        var numericValue = value.replace(/\D/g, ''); // Remove non-digits
        // Optional: Add thousands separators for display (more complex)
        // var formattedValue = numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        // $(this).val(formattedValue);
         $(this).val(numericValue); // Just keep numbers for easier processing
    });


    // --- User Dropdown Interaction ---
    // Using mouseenter/mouseleave for hover effect with slight delay
    $('.user-info').on('mouseenter', function() {
        $(this).find('.user-dropdown').stop(true, true).delay(100).fadeIn(150);
    }).on('mouseleave', function() {
        $(this).find('.user-dropdown').stop(true, true).delay(200).fadeOut(150);
    });

}); // End of $(document).ready()