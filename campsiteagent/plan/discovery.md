# Discovery Document - Campsite Agent V1

## Project Overview
**Project Name:** Campsite Agent  
**Version:** V1  
**Date:** December 2024  
**Target Deployment:** Ubuntu server at 192.168.0.205  

## DISCOVERY SUMMARY

The Campsite Agent is a web-based system designed to automatically monitor California State Park campground availability, specifically focusing on weekend availability (Friday and Saturday nights both available) at popular parks like San Onofre State Beach. The system will provide timely email notifications to registered users when weekend sites become available, with a focus on the critical 6AM-8AM PST booking window when new reservations open.

## Target Users

### Primary Users
- **Admin Users:** System administrators who can add new parks to monitor and manage system settings
- **Public Users:** Registered campers who want to receive notifications about weekend availability at monitored parks

### User Characteristics
- Campers looking for weekend camping opportunities at California State Parks
- Users comfortable with email-based notifications and web dashboard interfaces
- Users who understand the competitive nature of California State Park reservations

## Problem Being Solved

### Core Problem
Difficulty finding available weekend campsites at popular California State Parks due to:
- High demand for weekend camping spots
- Limited advance booking windows (6 months)
- New reservations opening at 8AM PST daily
- Brief availability windows when cancellations occur
- Time-consuming manual checking of ReserveCalifornia.com

### Pain Points
- Missing the 8AM PST booking window for new reservations
- Not knowing when cancellations create weekend availability
- Spending excessive time manually checking multiple dates
- Missing brief availability windows that get booked quickly

## Desired Outcome

### Primary Goals
- Automated monitoring of ReserveCalifornia.com for weekend availability
- Timely email notifications by 7:30AM PST when weekend sites are found
- User-friendly web dashboard for viewing results and managing preferences
- Secure user authentication system with role-based access
- Scalable system that can monitor multiple parks

### Success Metrics
- Successful scraping of ReserveCalifornia.com without rate limiting
- Email notifications delivered within 30 minutes of availability detection
- User registration and login functionality working reliably
- Web dashboard providing clear availability information
- System running reliably on Ubuntu server with PHP 7.4 and MySQL

## Success Criteria

### Technical Success
- System successfully scrapes ReserveCalifornia.com for San Onofre State Beach
- Weekend availability detection (Friday AND Saturday nights both available)
- Email notifications sent by 7:30AM PST
- Web dashboard accessible and functional
- MySQL database storing all availability data
- On-demand scraping capability working

### User Experience Success
- Users can register and verify accounts via email
- Passwordless login via email links working
- Users can toggle monitoring on/off
- Clear availability information displayed
- Admin users can manage parks and system settings

## Primary Use Cases

### User Management
1. **User Registration:** New users register with first name, last name, and email
2. **Email Verification:** Users click email link to activate account
3. **Passwordless Login:** Users enter email, receive login link via email
4. **Role Management:** Admin vs public user permissions

### Availability Monitoring
1. **Daily Automated Check:** 6AM PST availability check before 8AM booking window
2. **On-Demand Check:** Users can trigger immediate availability checks
3. **Cancellation Monitoring:** Continuous monitoring for cancellations throughout day
4. **Weekend Detection:** Identify sites with Friday AND Saturday night availability

### Notification System
1. **Email Notifications:** Send availability alerts by 7:30AM PST
2. **Web Dashboard:** Display availability results and user preferences
3. **User Controls:** Allow users to turn monitoring on/off

## Must-Have Features (V1)

### User Authentication & Management
- User registration system (first name, last name, email)
- Email verification for account activation
- Passwordless login via email links
- Admin and public user roles
- Admin-only park management capabilities

### Availability Monitoring
- ReserveCalifornia.com scraping for San Onofre State Beach
- Weekend availability detection (Friday AND Saturday nights both available)
- 6-month advance date range checking
- On-demand scraping capability
- Continuous monitoring for cancellations

### Notification & Data Management
- Email notifications by 7:30AM PST
- Web dashboard for viewing results
- MySQL database storage for all data
- Site number and type extraction
- All publicly available data capture
- User monitoring toggle (on/off)

### System Infrastructure
- Ubuntu server deployment at 192.168.0.205
- Web files in /var/www/campsiteagent.com/www
- Database: campsitechecker
- PHP 7.4 and MySQL compatibility

## Nice-to-Have Features (Future Versions)

### Enhanced Monitoring
- Multiple park monitoring (beyond San Onofre State Beach)
- Holiday weekend detection and monitoring
- Advanced filtering options for site types

### Additional Notifications
- Text message notifications
- Push notifications
- Custom notification preferences

### Advanced Features
- Historical availability data analysis
- Price tracking and alerts
- Integration with other reservation systems

## Constraints

### Technical Constraints
- Target deployment: Ubuntu server at 192.168.0.205
- Web files location: /var/www/campsiteagent.com/www
- Database name: campsitechecker
- PHP 7.4 and MySQL requirements
- 6AM daily check timing requirement
- 7:30AM notification deadline

### Operational Constraints
- Must respect ReserveCalifornia.com terms of service
- Need to avoid rate limiting or anti-bot measures
- Email delivery reliability requirements
- User email deliverability for login links

### Business Constraints
- Starting with single park (San Onofre State Beach)
- Weekend availability focus (Friday + Saturday nights)
- 6-month advance booking window

## Existing Alternatives

### Current Solutions
- Manual checking of ReserveCalifornia.com
- Third-party camping availability services
- Social media groups for camping availability alerts
- Personal spreadsheets for tracking availability

### Limitations of Alternatives
- Time-consuming manual processes
- Missed availability windows
- Limited coverage of parks
- No automated notification systems

## Risks & Assumptions

### Technical Risks
- ReserveCalifornia.com structure may change
- Rate limiting or anti-bot measures implementation
- Email delivery reliability issues
- Server uptime and reliability

### Business Risks
- Changes to ReserveCalifornia.com terms of service
- Competition from other automated services
- User adoption and engagement

### Assumptions
- ReserveCalifornia.com will maintain current structure
- Email delivery will be reliable
- Users will prefer email notifications
- Weekend availability (Friday + Saturday) is primary need
- 6-month advance booking window will remain consistent

## Implementation Notes

### Development Approach
- Start with San Onofre State Beach as proof of concept
- Build user authentication system first
- Implement basic scraping and notification system
- Add web dashboard for user management
- Test thoroughly before production deployment

### Deployment Strategy
- Local development and testing
- Staging environment on Ubuntu server
- Production deployment with monitoring
- Regular backups and maintenance procedures

---

**Document Status:** Complete  
**Next Phase:** PRD Creation  
**Approved By:** [To be filled]  
**Date Approved:** [To be filled]
