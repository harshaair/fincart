# FinCart Dataflow Diagram

## Simple Dataflow Diagram (Like Reference Image)

Copy this code and paste it into [Mermaid Live Editor](https://mermaid.live/) to generate the diagram image.

```mermaid
flowchart TD
    %% Entities (Rectangles)
    User[User]
    Admin[Admin]
    Professional[Tax Professional]
    
    %% Authentication & Profile Processes (Ovals)
    Login[Login]
    Signup[Signup]
    Verify[Verify]
    Profile[Profile]
    
    %% Main Dashboard Process
    Dashboard[Dashboard]
    
    %% Financial Processes (Ovals)
    Transactions[Transactions]
    Budget[Budget]
    Investments[Investments]
    Goals[Goals]
    Analytics[Analytics]
    
    %% Tax Processes (Ovals)
    TaxCalculator[Tax Calculator]
    TaxTracker[Tax Tracker]
    TaxSummary[Tax Summary]
    ITR[ITR Preparation]
    
    %% Professional Network Process
    Professionals[Professionals]
    Booking[Booking]
    
    %% Data Stores (Rectangles)
    UsersDB[(Users)]
    TransactionsDB[(Transactions)]
    BudgetDB[(Budgets)]
    InvestmentsDB[(Investments)]
    GoalsDB[(Goals)]
    TaxDB[(Tax Data)]
    ProfessionalsDB[(Professionals)]
    
    %% Notification
    Notification[Notification]
    
    %% Authentication Flow
    User —>|Credentials| Login
    User —>|Register| Signup
    Login —>|Verify| UsersDB
    Signup —>|Create| UsersDB
    UsersDB —>|User Data| Login
    Login —>|Session| Dashboard
    User —>|Update| Profile
    Profile —>|Save| UsersDB
    UsersDB —>|Read| Profile
    User —>|Verify| Verify
    Verify —>|Check| Admin
    
    %% Dashboard to Features
    Dashboard —> Transactions
    Dashboard —> Budget
    Dashboard —> Investments
    Dashboard —> Goals
    Dashboard —> Analytics
    Dashboard —> TaxCalculator
    Dashboard —> Professionals
    
    %% Transaction Flow
    Transactions —>|Save| TransactionsDB
    TransactionsDB —>|Read| Transactions
    TransactionsDB —>|Data| Analytics
    TransactionsDB —>|Compare| Budget
    
    %% Budget Flow
    Budget —>|Save| BudgetDB
    BudgetDB —>|Read| Budget
    Budget —>|Status| Notification
    
    %% Investment Flow
    Investments —>|Save| InvestmentsDB
    InvestmentsDB —>|Read| Investments
    InvestmentsDB —>|Data| Analytics
    
    %% Goals Flow
    Goals —>|Save| GoalsDB
    GoalsDB —>|Read| Goals
    TransactionsDB —>|Update| Goals
    Goals —>|Progress| Notification
    
    %% Tax Flow
    TaxCalculator —>|Calculate| TaxDB
    TaxTracker —>|Save| TaxDB
    TaxDB —>|Read| TaxTracker
    TaxDB —>|Data| TaxCalculator
    TaxCalculator —>|Summary| TaxSummary
    TaxSummary —>|Read| TaxDB
    TaxDB —>|Data| ITR
    ITR —>|Save| TaxDB
    
    %% Professional Flow
    Professionals —>|Search| ProfessionalsDB
    ProfessionalsDB —>|List| Professionals
    User —>|Book| Booking
    Booking —>|Save| ProfessionalsDB
    ProfessionalsDB —>|Notify| Notification
    Notification —>|Alert| User
    
    %% Analytics Flow
    TransactionsDB —>|All Data| Analytics
    InvestmentsDB —>|Data| Analytics
    BudgetDB —>|Data| Analytics
    Analytics —>|Reports| User
```

## How to Use

1. **Option 1 - Mermaid Live Editor:**
   - Go to https://mermaid.live/
   - Paste the Mermaid code above
   - Click "Actions" → "Download PNG" or "Download SVG"

2. **Option 2 - VS Code:**
   - Install "Markdown Preview Mermaid Support" extension
   - Create a `.md` file with the code
   - Preview and export as image

3. **Option 3 - GitHub:**
   - Create a markdown file with the code
   - Push to GitHub
   - GitHub will render it automatically

4. **Option 4 - Draw.io:**
   - Use the structure above as a guide
   - Manually recreate in Draw.io for more customization

## Diagram Components

- **Rectangles (Blue)**: External Entities (User, Admin, Tax Professional)
- **Ovals (Orange)**: Processes (Login, Dashboard, Transactions, etc.)
- **Cylinders (Purple)**: Data Stores (Database tables)

## Data Flow Legend

- **Solid Arrows**: Data flow direction
- **Labels on Arrows**: Type of data being transferred
- **Colors**: Different component types for clarity
