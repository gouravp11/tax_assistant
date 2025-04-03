# Tax Assistant

A simple PHP-based project to manage user tax-related data.

## Dependencies

- **PHP**: Version 8.2 or higher
- **MariaDB/MySQL**: Version 10.4 or higher
- **Composer**: For managing PHP dependencies
- **XAMPP**: For local development (optional)

## Installation

1. Clone the repository:
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
   - Import the `tax_assistant.sql` file located in the project directory.

4. Configure the environment:
   - Create a `.env` file in the project root.
   - Add the following configuration:
     ```
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=tax_assistant
     DB_USERNAME=root
     DB_PASSWORD=
     ```
   - Update the `config.php` file with your API key and other sensitive information.

## Running the Project

1. Start your local server (e.g., XAMPP or built-in PHP server):
   ```bash
   php -S localhost:8000
   ```

2. Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

## Notes

- Ensure the `tax_assistant.sql` and `config.php` files are excluded from version control as they contain sensitive data.
- Update the `.env` file and `config.php` with your credentials before running the project.

## License

This project is licensed under the MIT License.
