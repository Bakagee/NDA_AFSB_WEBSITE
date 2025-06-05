# NDA AFSB Screening System

## Overview
The Nigerian Defence Academy Armed Forces Selection Board (NDA AFSB) Screening System is a comprehensive web-based platform designed to streamline and manage the candidate screening process for the Nigerian Defence Academy. This system facilitates the efficient evaluation of candidates across multiple screening stages, from initial documentation to final board interviews.

## Features

### Admin Module
- **Dashboard**: Overview of screening statistics and system status
- **Candidate Management**: 
  - View and manage all candidates
  - Track candidate progress across stages
  - Generate comprehensive reports
- **Officer Management**:
  - Create and manage screening officers
  - Assign officers to specific states
  - Monitor officer activities
- **Stage Management**:
  - Enable/disable screening stages
  - Configure stage parameters
  - Monitor stage progress
- **Report Generation**:
  - Generate detailed screening reports
  - Export data in various formats
  - View final scores and rankings

### Officer Module
- **Dashboard**: Overview of assigned state's candidates and progress
- **Candidate Screening**:
  - Documentation verification
  - Medical examination
  - Physical assessment
  - Sand modelling evaluation
  - Board interview
- **Profile Management**:
  - Update personal information
  - Change password
  - Manage profile picture

## Technical Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- SSL certificate (for secure connections)
- Minimum 2GB RAM
- 20GB storage space

### Client Requirements
- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Stable internet connection
- Minimum screen resolution: 1366x768

## Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/Bakagee/NDA_AFSB_WEBSITE.git
   ```

2. **Database Setup**
   - Create a new MySQL database
   - Import the database schema from `database/schema.sql`
   - Configure database connection in `database_connection.php`

3. **Configuration**
   - Set up your web server (Apache/Nginx)
   - Configure virtual host to point to the project directory
   - Ensure proper file permissions (755 for directories, 644 for files)
   - Update configuration files with your settings

4. **Directory Structure**
   ```
   nda/
   ├── admin/              # Admin module files
   ├── officer/            # Officer module files
   ├── img/               # Image assets
   ├── database/          # Database files
   ├── includes/          # Shared PHP files
   └── uploads/           # Uploaded files
   ```

## Security Features

- Secure session management
- Password hashing using bcrypt
- Input validation and sanitization
- CSRF protection
- XSS prevention
- SQL injection prevention
- Role-based access control
- Secure file upload handling

## Usage

### Admin Access
1. Navigate to the admin login page
2. Enter admin credentials
3. Access the admin dashboard
4. Manage candidates, officers, and screening stages

### Officer Access
1. Navigate to the officer login page
2. Enter officer credentials
3. Access the officer dashboard
4. Begin screening candidates in assigned state

## Screening Process

1. **Documentation Stage**
   - Verify candidate credentials
   - Check required documents
   - Flag any discrepancies

2. **Medical Examination**
   - Record medical history
   - Conduct physical examination
   - Document fitness status

3. **Physical Assessment**
   - Evaluate physical fitness
   - Record performance metrics
   - Document assessment results

4. **Sand Modelling**
   - Assess spatial awareness
   - Evaluate problem-solving skills
   - Record performance scores

5. **Board Interview**
   - Conduct final interview
   - Evaluate candidate suitability
   - Record interview scores

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Support

For technical support, contact:
- Email: support@nda.mil.ng
- Phone: +234 (0) XXXX XXXX

## License

This project is proprietary and confidential. Unauthorized copying, distribution, or use is strictly prohibited.

## Credits

Developed for the Nigerian Defence Academy Armed Forces Selection Board.

## Version History

- v1.0.0 (2024) - Initial release
  - Basic screening functionality
  - Admin and officer modules
  - Report generation
  - Stage management

## Future Enhancements

- Mobile application development
- Real-time notifications
- Advanced analytics dashboard
- Integration with other military systems
- Automated scheduling system
- Enhanced reporting capabilities

## Maintenance

Regular maintenance tasks:
- Database backups
- Log rotation
- Security updates
- Performance optimization
- User activity monitoring

## Troubleshooting

Common issues and solutions:
1. **Login Issues**
   - Clear browser cache
   - Check internet connection
   - Verify credentials

2. **Upload Problems**
   - Check file size limits
   - Verify file types
   - Ensure directory permissions

3. **Performance Issues**
   - Clear browser cache
   - Check server resources
   - Optimize database queries

## Best Practices

1. **Security**
   - Regular password changes
   - Secure file handling
   - Data encryption
   - Access control

2. **Data Management**
   - Regular backups
   - Data validation
   - Error logging
   - Audit trails

3. **User Management**
   - Role-based access
   - Activity monitoring
   - Session management
   - Password policies 