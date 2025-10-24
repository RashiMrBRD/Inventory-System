# Contributing to Inventory Management System

Thank you for considering contributing to this project! We welcome contributions from the community.

## How to Contribute

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When creating a bug report, include:

- **Description**: Clear description of the bug
- **Steps to reproduce**: Detailed steps to reproduce the behavior
- **Expected behavior**: What you expected to happen
- **Actual behavior**: What actually happened
- **Environment**: OS, PHP version, MongoDB version, browser
- **Screenshots**: If applicable

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Clear title**: Descriptive title for the enhancement
- **Description**: Detailed description of the proposed feature
- **Use case**: Why this enhancement would be useful
- **Implementation ideas**: Optional suggestions for implementation

### Pull Requests

1. **Fork the repository** and create your branch from `master`
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Follow existing code style
   - Write clear, concise commit messages
   - Add tests if applicable
   - Update documentation as needed

3. **Test your changes**
   ```bash
   # Install dependencies
   composer install
   
   # Run the application locally
   docker-compose -f container/docker-compose.yml up -d
   ```

4. **Commit your changes**
   ```bash
   git commit -m "feat: add new feature"
   ```
   
   Use conventional commit format:
   - `feat:` New feature
   - `fix:` Bug fix
   - `docs:` Documentation changes
   - `style:` Code style changes (formatting)
   - `refactor:` Code refactoring
   - `test:` Adding tests
   - `chore:` Maintenance tasks

5. **Push to your fork**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Open a Pull Request**
   - Provide clear description of changes
   - Reference related issues
   - Ensure CI checks pass

## Development Setup

### Prerequisites
- PHP 8.2+
- MongoDB 7.0+
- Docker & Docker Compose
- Composer

### Local Development

```bash
# Clone repository
git clone https://github.com/RashiMrBRD/Inventory-System.git
cd inventory_demo

# Install dependencies
composer install

# Start Docker containers
docker-compose -f container/docker-compose.yml up -d

# Access application
# Web: http://localhost:8082
# Mongo Express: http://localhost:8081
```

## Code Style

- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions small and focused
- Use type hints where applicable

## Testing

- Test all changes thoroughly before submitting
- Ensure existing functionality is not broken
- Add tests for new features when possible

## Documentation

- Update README.md if adding new features
- Document new API endpoints
- Add inline comments for complex code
- Update relevant markdown files in `/docs`

## Project Structure

```
inventory_demo/
├── api/            # RESTful API endpoints
├── config/         # Configuration files
├── container/      # Docker setup
├── public/         # Web root
├── src/            # PHP classes (MVC)
└── var/            # Runtime files
```

## Questions?

Feel free to open an issue for questions or discussions about contributions.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
