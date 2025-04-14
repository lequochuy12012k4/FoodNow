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


$(function () { // Use jQuery's ready function

    // --- Autocomplete Logic ---
    // Check if the variable exists and is an array before initializing
    if (typeof availableFoodsFromPHP !== 'undefined' && Array.isArray(availableFoodsFromPHP)) {

        console.log('Initializing jQuery Autocomplete with data:', availableFoodsFromPHP); // Debug log

        if (availableFoodsFromPHP.length === 0) {
            console.warn("Autocomplete data array (availableFoodsFromPHP) received from PHP is empty.");
            // Optional: Inform the user or disable the input
            // $("#searchfoods").attr('placeholder', 'No food data found');
        }

        $("#searchfoods").autocomplete({
            source: availableFoodsFromPHP, // *** Use the PHP-generated variable ***
            minLength: 1, // Start searching after 1 character
            select: function (event, ui) {
                // ui.item contains the selected object { label, value, image, price, url }
                var selectedFood = ui.item.value;
                var selectedUrl = ui.item.url; // Get the URL

                console.log("Selected item:", ui.item); // Log the selected item object

                // **Redirect if URL exists**
                if (selectedUrl) {
                    window.location.href = selectedUrl; // Redirect to the detail page
                    return false; // Prevent the input field from being updated with the value after redirection
                } else {
                    // Fallback if no URL (e.g., just show an alert or do nothing)
                    console.warn("No URL found for selected food:", ui.item);
                    // alert("Bạn đã chọn: " + selectedFood + " - Giá: " . ui.item.price);
                    // If you don't redirect, you might want the input field to show the selected name,
                    // so don't return false in this case.
                }
                // If not redirecting, allow the default behavior (fill input with ui.item.value)
            }
        }).autocomplete("instance")._renderItem = function (ul, item) {
            // Customize how each item is displayed in the suggestion list
            var itemContent = `
                <div class="div-hover" style="display: flex; align-items: center; gap: 10px; padding: 5px 8px; border-radius: 4px; cursor: pointer; background-color: #fff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); color: black;">
                    <img src="${item.image}" alt="${item.label}" style="width: 45px; height: 45px; object-fit: cover; border-radius: 4px; flex-shrink: 0; border: 1px solid #eee;">
                    <div style="display: flex; flex-direction: column; flex-grow: 1; line-height: 1.3;">
                        <div style=" font-family: 'Montserrat', sans-serif;">${item.label}</div>
                        <div style="font-size: 0.85em; color: #444;">${item.price}</div>
                    </div>
                </div>`;

            return $("<li>")
                .append(itemContent) // Use the formatted HTML string
                .appendTo(ul);
        };

        // Optional: Trigger search on focus (can be annoying, use with caution)
        // $("#searchfoods").on("focus", function() {
        //     $(this).autocomplete("search", $(this).val());
        // });

    } else {
        // This case means the PHP block didn't run correctly or didn't define the variable
        console.error("Autocomplete data (availableFoodsFromPHP) is missing or not an array. Check PHP execution and the inline script in the HTML source. Autocomplete disabled.");
        // Optionally disable the input or show a different placeholder
         $("#searchfoods").prop('disabled', true).attr('placeholder', 'Search unavailable');
    }

    // --- End of Autocomplete Logic ---

});
$(document).ready(function() {

    // --- Live Search Functionality ---
    const searchInput = $('#admin-search-food');
    const tableBody = $('#food-table-body');
    const tableRows = tableBody.find('tr'); // Get all initial data rows
    const noResultsRow = $('#no-results-row');

    searchInput.on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        let visibleRowCount = 0;

        // Hide the "no results" row initially for each search action
        noResultsRow.hide();

        tableRows.each(function() {
            const row = $(this);
            // Make sure we don't try to filter the "no results" row itself
            if (row.attr('id') === 'no-results-row') {
                return; // Skip this row
            }

            // Find the cell containing the food name (assuming it's the 2nd column)
            const foodNameCell = row.find('td:nth-child(2)'); // Index 2 (1-based)
            const foodName = foodNameCell.text().toLowerCase();

            // Check if the food name includes the search term
            const isMatch = foodName.includes(searchTerm);

            // Show or hide the row based on the match
            row.toggle(isMatch); // toggle(true) shows, toggle(false) hides

            if (isMatch) {
                visibleRowCount++;
            }
        });

        // Show the "no results" row if no data rows are visible AND there's a search term
        if (visibleRowCount === 0 && searchTerm !== '') {
            noResultsRow.show();
        }
    });

    // --- Existing Modal Logic (Keep this if you have it) ---
    const modal = $('#add-item-modal');
    const showModalBtn = $('#show-add-modal-btn');
    const closeModalBtn = $('#close-modal-btn');
    const overlay = $('.modal-overlay');
    const form = $('#add-item-form');
    const modalTitle = $('#modal-title');
    const formAction = $('#form-action');
    const editItemId = $('#edit-item-id');
    const currentImageFilename = $('#current-image-filename');
    const currentImagePreview = $('#current-image-preview');
    const submitButtonText = $('#modal-submit-button-text');

    // Function to open the modal
    function openModal() {
        modal.fadeIn(200);
    }

    // Function to close the modal
    function closeModal() {
        modal.fadeOut(200);
        resetForm(); // Reset form when closing
    }

    // Function to reset the form
    function resetForm() {
        form[0].reset(); // Reset native form fields
        modalTitle.text('Thêm Món ăn mới');
        formAction.val('add');
        editItemId.val('');
        currentImageFilename.val('');
        currentImagePreview.html(''); // Clear image preview
        submitButtonText.text('Thêm món ăn');
        $('#add-item-form-message').hide().removeClass('alert-success alert-danger').text('');
    }

    // Event listeners for modal
    showModalBtn.on('click', function() {
        resetForm(); // Ensure form is clean for adding
        openModal();
    });
    closeModalBtn.on('click', closeModal);
    overlay.on('click', closeModal);

    // Handle Escape key to close modal
    $(document).on('keydown', function(event) {
        if (event.key === "Escape" && modal.is(':visible')) {
            closeModal();
        }
    });

    // --- Form submission (Keep or adapt your existing submission logic) ---
    // Example:
    // form.on('submit', function(e) {
    //     // Add your AJAX submission or standard form handling here
    //     // If using standard POST, the page will reload anyway.
    //     // If using AJAX, handle success/error messages and potentially update table.
    //     // Example: Prevent default if using AJAX
    //     // e.preventDefault();
    //     // console.log("Form submitted (implement AJAX or let default happen)");
    // });


}); // End of $(document).ready()


// --- Edit Modal Function (Keep outside document.ready) ---
// Make sure this function is globally accessible if called via inline onclick
function openEditModal(foodData) {
    const modal = $('#add-item-modal');
    const form = $('#add-item-form');
    const modalTitle = $('#modal-title');
    const formAction = $('#form-action');
    const editItemId = $('#edit-item-id');
    const currentImageFilename = $('#current-image-filename');
    const currentImagePreview = $('#current-image-preview');
    const submitButtonText = $('#modal-submit-button-text');

    // Reset form before populating
    form[0].reset();
    $('#add-item-form-message').hide().removeClass('alert-success alert-danger').text('');


    // Populate form fields
    modalTitle.text('Chỉnh sửa Món ăn');
    formAction.val('edit');
    editItemId.val(foodData.id);
    $('#item-name').val(foodData.name);
    $('#item-type').val(foodData.type);
    $('#item-price').val(foodData.price); // Ensure price doesn't have formatting here
    $('#item-rating').val(foodData.rate);
    $('#item-description').val(foodData.description || ''); // Handle null description

    // Handle image preview
    currentImageFilename.val(foodData.image || '');
    if (foodData.image) {
        // Adjust path as necessary if $uploadDir is not directly accessible here
        // Assuming 'uploads/' is the cor   rect relative path from the web root
        currentImagePreview.html(`Ảnh hiện tại: <img src="uploads/${foodData.image}" alt="Current image" style="max-height: 50px; vertical-align: middle; margin-left: 10px;">`);
    } else {
        currentImagePreview.html('Chưa có ảnh.');
    }


    submitButtonText.text('Lưu thay đổi');

    modal.fadeIn(200); // Show the modal
}