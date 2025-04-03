// Log when the script is loaded
console.log("Script loaded");

document.addEventListener("DOMContentLoaded", () => {
    console.log("DOM fully loaded and parsed");

    // Toggle visibility of the add and update forms
    const showAddFormButton = document.getElementById("show-add-form-btn");
    const showUpdateFormButton = document.getElementById("show-update-form-btn");
    const userDetailsForm = document.querySelector(".user-details");

    if (showAddFormButton) {
        showAddFormButton.addEventListener("click", () => {
            console.log("Show add form button clicked");
            userDetailsForm.classList.remove("hidden"); // Show the form
            showAddFormButton.classList.add("hidden"); // Hide the add button
            if (showUpdateFormButton) showUpdateFormButton.classList.add("hidden"); // Ensure update button is hidden
        });
    }

    if (showUpdateFormButton) {
        showUpdateFormButton.addEventListener("click", () => {
            console.log("Show update form button clicked");
            userDetailsForm.classList.remove("hidden"); // Show the form
            showUpdateFormButton.classList.add("hidden"); // Hide the update button
        });
    }

    // Hide the form when clicking outside
    document.addEventListener("click", (event) => {
        if (chatbotModal && !chatbotModal.classList.contains("hidden")) {
            return; // Skip logic if the chatbot modal is open
        }

        if (!userDetailsForm.contains(event.target) && !event.target.matches("#show-add-form-btn, #show-update-form-btn")) {
            console.log("Clicked outside the form, hiding it");
            userDetailsForm.classList.add("hidden"); // Hide the form
            if (showAddFormButton && userDetailsForm.dataset.detailsFilled !== "true") {
                showAddFormButton.classList.remove("hidden"); // Show the add button if no details are filled
            }
            if (showUpdateFormButton && userDetailsForm.dataset.detailsFilled === "true") {
                showUpdateFormButton.classList.remove("hidden"); // Show the update button if details are filled
            }
        }
    });

    // Add more income fields dynamically
    const addMoreButton = document.querySelector(".income-sources button");
    const incomeSourcesContainer = document.querySelector(".income-sources");
    let incomeCount = 1;

    addMoreButton.addEventListener("click", (event) => {
        event.preventDefault();

        incomeCount++;
        const newIncomeDiv = document.createElement("div");
        newIncomeDiv.className = `income-${incomeCount} flex gap-2 mb-2`;

        newIncomeDiv.innerHTML = `
            <input
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                type="text" id="income-category-${incomeCount}" name="income-category-${incomeCount}" placeholder="Income Category">
            <input
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                type="number" id="income-${incomeCount}" name="income-${incomeCount}" placeholder="00000">
        `;

        incomeSourcesContainer.insertBefore(newIncomeDiv, addMoreButton);
    });

    // Add more deduction fields dynamically
    const addDeductionButton = document.querySelector(".deductions button");
    const deductionsContainer = document.querySelector(".deductions");
    let deductionCount = 1;

    addDeductionButton.addEventListener("click", (event) => {
        event.preventDefault();

        deductionCount++;
        const newDeductionDiv = document.createElement("div");
        newDeductionDiv.className = `deduction-${deductionCount} flex gap-2 mb-2`;

        newDeductionDiv.innerHTML = `
            <input
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                type="text" id="deduction-category-${deductionCount}" name="deduction-category-${deductionCount}" placeholder="Deduction Category">
            <input
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                type="number" id="deduction-${deductionCount}" name="deduction-${deductionCount}" placeholder="00000">
        `;

        deductionsContainer.insertBefore(newDeductionDiv, addDeductionButton);
    });

    // Show loader when the Analyze button is clicked
    const analyzeForm = document.querySelector("form[action='index.php'][method='POST']");
    const loader = document.getElementById("loader");

    if (!loader) {
        console.error("Loader element not found in the DOM.");
        return;
    }

    analyzeForm.addEventListener("submit", (event) => {
        console.log("Analyze button clicked, showing loader...");
        loader.classList.remove("hidden"); // Show the loader
        userDetailsForm.dataset.detailsFilled = "true"; // Mark details as filled after submission
        if (showUpdateFormButton) showUpdateFormButton.classList.remove("hidden"); // Show the update button
        if (showAddFormButton) showAddFormButton.classList.add("hidden"); // Hide the add button
        userDetailsForm.classList.add("hidden"); // Hide the form after submission
    });

    // Chatbot modal functionality
    const openChatbotBtn = document.getElementById("open-chatbot-btn");
    const chatbotModal = document.getElementById("chatbot-modal");

    openChatbotBtn.addEventListener("click", () => {
        chatbotModal.classList.remove("hidden"); // Show the chatbot modal
        if (showAddFormButton) showAddFormButton.classList.add("hidden"); // Hide the add button
        if (showUpdateFormButton) showUpdateFormButton.classList.add("hidden"); // Hide the update button
        if (userDetailsForm) userDetailsForm.classList.add("hidden"); // Ensure the form stays hidden
    });

    // Ensure the chatbot modal does not interfere with button visibility
    chatbotModal.addEventListener("click", (event) => {
        if (event.target === chatbotModal) {
            chatbotModal.classList.add("hidden"); // Close the chatbot modal
            // Do not change the visibility of the add or update buttons when closing the chatbot
        }
    });
    
});