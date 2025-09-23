# VSLA Voting & Election Management System

## Overview

The Voting & Election Management System (VEMS) is a comprehensive module that extends the VSLA (Village Savings and Loan Association) functionality to enable transparent, flexible, and auditable elections and decision-making processes within groups. This system allows members of a tenant account (VSLA group) to participate in elections for leadership roles or general voting on policy, fund usage, rules, and other group matters.

## Features

### 1. Election Management
- **Position-based Elections**: Create elections for specific positions (Chairperson, Treasurer, Secretary, etc.)
- **Multiple Election Types**:
  - Single-winner elections (e.g., Chairperson)
  - Multi-position elections (e.g., committee members)
  - Referendum-style votes (Yes/No on proposals)
- **Configurable Positions**: Define voting positions with descriptions and maximum winners

### 2. Voting Mechanisms
- **Majority Vote**: Candidate/option with most votes wins
- **Ranked Choice Vote**: Members rank preferences; winner decided after redistribution
- **Weighted Vote**: Votes can be weighted based on member criteria (shares, membership duration, etc.)

### 3. Privacy & Transparency Modes
- **Private Voting**: Only results are visible; individual choices remain anonymous
- **Public Voting**: Both results and how members voted are visible to the group
- **Hybrid Mode**: Admins see detailed votes, members only see anonymized totals

### 4. Member Voting Interface
- **Active Elections**: List of ongoing votes to participate in
- **Voting History**: Past votes, results, and participation record
- **Results View**: Outcomes of elections/issues they voted on
- **Intuitive Interface**: Easy-to-use voting forms with clear instructions

### 5. Result Management
- **Real-time Tallying**: Live vote counting with progress indicators
- **Final Results**: Locked results after election closure
- **Audit Logging**: Complete transparency and compliance tracking
- **Statistical Analysis**: Participation rates, vote breakdowns, and trends

### 6. Notifications
- **Election Notifications**: Members notified when new votes/elections are created
- **Voting Reminders**: Reminders for pending votes
- **Results Announcements**: Notifications when results are available

## Database Schema

### Core Tables

1. **voting_positions**: Defines available positions for elections
2. **elections**: Main election records with configuration
3. **candidates**: Election candidates linked to members
4. **votes**: Individual vote records
5. **election_results**: Calculated results and statistics
6. **voting_audit_logs**: Complete audit trail

### Relationships

- Elections belong to tenants (VSLA groups)
- Candidates are linked to members and elections
- Votes are linked to members, elections, and candidates
- Results are calculated and stored for each election

## Installation & Setup

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Enable VSLA Module
Ensure VSLA is enabled for your tenant:
```php
$tenant->update(['vsla_enabled' => true]);
```

### 3. Access Voting System
- **Admin Access**: Navigate to "Voting & Elections" in the admin menu
- **Member Access**: Navigate to "Voting & Elections" in the customer portal

## Usage Guide

### For Administrators

#### Creating Voting Positions
1. Go to "Voting & Elections" → "Voting Positions"
2. Click "Create Position"
3. Fill in position details:
   - Name (e.g., "Chairperson")
   - Description
   - Maximum winners (usually 1 for single positions)

#### Creating Elections
1. Go to "Voting & Elections" → "Elections"
2. Click "Create Election"
3. Configure election settings:
   - Title and description
   - Election type (single winner, multi-position, referendum)
   - Voting mechanism (majority, ranked choice, weighted)
   - Privacy mode (private, public, hybrid)
   - Voting period (start and end dates)
   - Options (allow abstain, weighted voting)

#### Managing Candidates
1. Open an election in draft status
2. Click "Manage Candidates"
3. Add members as candidates
4. Include candidate bios and manifestos

#### Starting Elections
1. Ensure all candidates are added
2. Click "Start Election" on the election details page
3. Members will be notified automatically

#### Closing Elections
1. Click "Close Election" when voting period ends
2. Results will be calculated automatically
3. Members will be notified of results

### For Members

#### Voting in Elections
1. Go to "Voting & Elections" → "Active Elections"
2. Click "Vote Now" on an active election
3. Select your choice(s) based on election type:
   - **Referendum**: Choose Yes/No/Abstain
   - **Single Winner**: Select one candidate
   - **Multi-position**: Select multiple candidates
   - **Ranked Choice**: Rank candidates in order of preference

#### Viewing Results
1. Go to "Voting & Elections" → "Election Results"
2. View detailed results and statistics
3. See participation rates and vote breakdowns

## API Endpoints

### Admin Endpoints
- `GET /voting/positions` - List voting positions
- `POST /voting/positions` - Create voting position
- `GET /voting/elections` - List elections
- `POST /voting/elections` - Create election
- `POST /voting/elections/{id}/start` - Start election
- `POST /voting/elections/{id}/close` - Close election

### Member Endpoints
- `GET /voting/elections` - List elections
- `GET /voting/elections/{id}` - View election details
- `GET /voting/elections/{id}/vote` - Vote in election
- `POST /voting/elections/{id}/vote` - Submit vote
- `GET /voting/elections/{id}/results` - View results

## Security Features

### Authorization
- Role-based access control
- Tenant isolation
- Member verification for voting

### Audit Trail
- Complete logging of all voting activities
- IP address and user agent tracking
- Immutable vote records

### Data Integrity
- Unique vote constraints (one vote per member per election)
- Encrypted vote storage
- Tamper-proof result calculation

## Customization

### Vote Weighting
Modify the `calculateVoteWeight()` method in `VotingController` to implement custom weighting logic based on:
- Member shares
- Membership duration
- Contribution levels
- Other criteria

### Notification Templates
Customize notification emails by modifying:
- `ElectionCreatedNotification`
- `ElectionResultsNotification`

### UI Themes
All views are built with Bootstrap and can be customized by modifying the Blade templates in `resources/views/backend/voting/`.

## Testing

Run the test suite:
```bash
php artisan test tests/Feature/VotingTest.php
```

The test suite covers:
- Position creation
- Election creation
- Voting functionality
- Result calculation
- Security constraints

## Troubleshooting

### Common Issues

1. **Members can't vote**: Ensure they are active members of the tenant
2. **Elections not starting**: Check that all required fields are filled
3. **Results not calculating**: Verify election is closed and has votes
4. **Notifications not sending**: Check mail configuration

### Debug Mode
Enable debug logging by setting `LOG_LEVEL=debug` in your `.env` file.

## Future Enhancements

- **Mobile App Integration**: Native mobile voting interface
- **Advanced Analytics**: Detailed voting patterns and insights
- **Integration APIs**: Connect with external voting systems
- **Multi-language Support**: Localized voting interfaces
- **Advanced Security**: Blockchain-based vote verification

## Support

For technical support or feature requests, please contact the development team or create an issue in the project repository.

## License

This voting system is part of the IntelliCash VSLA platform and follows the same licensing terms.
