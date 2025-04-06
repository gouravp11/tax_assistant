# Tax Assistant

A simple PHP-based project to manage user tax-related data.

## Dependencies

- **PHP**: Version 8.2 or higher
- **MySQL**: Version 10.4 or higher
- **Composer**: For managing PHP dependencies
- **XAMPP**: For local development (optional)

## Installation

1. Clone the repository into your /xampp/htdocs:
   ```bash
   git clone https://github.com/your-repo/tax_assistant.git
   cd tax_assistant
   ```

2. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```

3. Import the database:
   - Open your database management tool (e.g., phpMyAdmin).
   - Create a database named `tax_assistant`.

4. Configure the environment:
   - Update the `config.php` file with your API key and other sensitive information.
   - Add the following configuration:
     ```
     <?php
     define("GEMINI_API_KEY", "your-generated-gemini-api-key")
     ?>

     ```
   

## Running the Project

1. Start your local server (e.g., XAMPP or built-in PHP server):

2. Open your browser and navigate to:
   ```
   localhost:/project-directory-in-htdocs/index.php
   ```

## Notes

- Ensure the `config.php` files are excluded from version control as they contain sensitive data.
- Update the `config.php` with your credentials before running the project.

## License

This project is licensed under the MIT License.
