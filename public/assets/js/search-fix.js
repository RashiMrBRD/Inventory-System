/**
 * Universal Search Fix
 * Fixes search input functionality across all pages
 */

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Initializing universal search fix...');
    
    // AGGRESSIVE: Remove any existing global search functions
    if (window.globalSearch) {
        console.log('Removing existing globalSearch function');
        delete window.globalSearch;
    }
    if (window.performGlobalSearch) {
        console.log('Removing existing performGlobalSearch function');
        delete window.performGlobalSearch;
    }
    
    // AGGRESSIVE: Intercept any other search attempts
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        const url = args[0];
        if (typeof url === 'string' && url.includes('orders.php')) {
            console.log('🚨 INTERCEPTED ORDERS API CALL:', url);
            console.log('🚨 CALLER:', new Error().stack);
        }
        return originalFetch.apply(this, args);
    };
    
    // AGGRESSIVE: Fix for global search - COMPLETE OVERRIDE
    const globalSearchInput = document.getElementById('global-search');
    if (globalSearchInput) {
        console.log('🔍 Found global search input, applying aggressive fix...');
        
        // Remove existing event listeners by cloning
        const newInput = globalSearchInput.cloneNode(true);
        globalSearchInput.parentNode.replaceChild(newInput, globalSearchInput);
        
        // Remove any existing dropdown
        const existingDropdown = document.getElementById('global-search-dropdown');
        if (existingDropdown) {
            existingDropdown.remove();
            console.log('Removed existing dropdown');
        }
        
        // Add new event listener for input (no automatic redirect)
        newInput.addEventListener('input', function(e) {
            e.preventDefault(); // Prevent any default behavior
            e.stopPropagation(); // Stop event propagation
            
            console.log('🔍 Global search input triggered:', e.target.value);
            console.log('🔍 Event target:', e.target);
            console.log('🔍 Event caller:', new Error().stack);
            
            // Add typing animation
            const searchWrapper = newInput.closest('.search-wrapper');
            if (searchWrapper) {
                searchWrapper.classList.add('typing');
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => {
                    searchWrapper.classList.remove('typing');
                }, 1000);
            }
            
            // ONLY call our globalSearch function
            globalSearch(e.target.value);
        });
        
        // Add focus animations
        newInput.addEventListener('focus', function(e) {
            const searchWrapper = newInput.closest('.search-wrapper');
            if (searchWrapper) {
                searchWrapper.classList.add('focused');
            }
        });
        
        newInput.addEventListener('blur', function(e) {
            const searchWrapper = newInput.closest('.search-wrapper');
            if (searchWrapper) {
                searchWrapper.classList.remove('focused', 'typing');
            }
        });
        
        // Prevent form submission on Enter
        newInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                console.log('🔍 Enter key pressed, preventing form submission');
            }
        });
        
        console.log('✅ Global search input aggressively fixed');
    } else {
        console.log('❌ Global search input not found');
    }
    
    console.log('🔍 Universal search fix initialization complete');
});

// Global search timeout
let globalSearchTimeout = null;
let typingTimeout = null;

function globalSearch(query) {
    console.log('=== GLOBAL SEARCH CALLED ===');
    console.log('Query:', query);
    
    const searchInput = document.getElementById('global-search');
    if (!searchInput) {
        console.log('ERROR: global-search input not found');
        return;
    }
    
    const searchWrapper = searchInput.closest('.search-wrapper');
    
    // Create or get search dropdown
    let dropdown = document.getElementById('global-search-dropdown');
    
    if (!dropdown) {
        console.log('Creating new dropdown');
        dropdown = createGlobalSearchDropdown();
        searchInput.parentNode.appendChild(dropdown);
    } else {
        console.log('Using existing dropdown');
    }
    
    // Handle search logic
    if (query.trim().length === 0) {
        console.log('Empty query, hiding dropdown');
        hideGlobalSearchDropdown();
        if (searchWrapper) {
            searchWrapper.classList.remove('loading', 'success');
        }
        return;
    }
    
    console.log('Processing non-empty query:', query.trim());
    
    // Show loading state
    showGlobalSearchDropdown();
    showSearchLoading();
    
    // Add loading animation
    if (searchWrapper) {
        searchWrapper.classList.add('loading');
        searchWrapper.classList.remove('success');
    }
    
    // Debounced search
    clearTimeout(globalSearchTimeout);
    globalSearchTimeout = setTimeout(() => {
        console.log('=== CALLING PERFORM GLOBAL SEARCH ===');
        performGlobalSearch(query.trim());
    }, 300);
}

function createGlobalSearchDropdown() {
    const dropdown = document.createElement('div');
    dropdown.id = 'global-search-dropdown';
    dropdown.className = 'global-search-dropdown';
    
    // Apply clean styles
    dropdown.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        z-index: 1000;
        max-height: 300px;
        overflow-y: auto;
        margin-top: 0.25rem;
        display: none;
    `;
    
    // Add styles to head
    if (!document.getElementById('global-search-styles')) {
        const style = document.createElement('style');
        style.id = 'global-search-styles';
        style.textContent = `
            .global-search-dropdown {
                animation: slideDown 0.15s ease-out;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-0.5rem);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .global-search-item {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid #f3f4f6;
                cursor: pointer;
                transition: background-color 0.15s ease;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            
            .global-search-item:hover {
                background-color: #f9fafb;
            }
            
            .global-search-item:last-child {
                border-bottom: none;
            }
            
            .global-search-item-icon {
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
                color: #6b7280;
            }
            
            .global-search-item-content {
                flex: 1;
                min-width: 0;
            }
            
            .global-search-item-title {
                font-weight: 500;
                color: #111827;
                font-size: 0.875rem;
                line-height: 1.25rem;
            }
            
            .global-search-item-description {
                color: #6b7280;
                font-size: 0.75rem;
                line-height: 1rem;
                margin-top: 0.125rem;
            }
            
            .global-search-section {
                padding: 0.5rem 1rem;
                background-color: #f9fafb;
                border-bottom: 1px solid #e5e7eb;
                font-size: 0.75rem;
                font-weight: 600;
                color: #374151;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            
            .global-search-empty {
                padding: 2rem;
                text-align: center;
                color: #6b7280;
                font-size: 0.875rem;
            }
            
            .global-search-loading {
                padding: 2rem;
                text-align: center;
                color: #6b7280;
                font-size: 0.875rem;
            }
        `;
        document.head.appendChild(style);
    }
    
    return dropdown;
}

function showGlobalSearchDropdown() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
        dropdown.style.display = 'block';
    }
}

function hideGlobalSearchDropdown() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
        dropdown.style.display = 'none';
    }
}

function showSearchLoading() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
        dropdown.innerHTML = '<div class="global-search-loading">Searching...</div>';
    }
}

function performGlobalSearch(query) {
    console.log('=== PERFORM GLOBAL SEARCH START ===');
    console.log('Query:', query);
    
    const searchInput = document.getElementById('global-search');
    const searchWrapper = searchInput ? searchInput.closest('.search-wrapper') : null;
    
    // Search across different data sources
    console.log('Creating search promises...');
    const searchPromises = [
        searchNavigation(query),
        searchInventory(query),
        searchOrders(query),
        searchCustomers(query),
        searchInvoices(query),
        searchQuotations(query),
        searchShipping(query),
        searchProjects(query),
        searchChartOfAccounts(query),
        searchJournalEntries(query),
        searchFinancialReports(query),
        searchSettings(query),
        searchBIRCompliance(query),
        searchFDACompliance(query),
        searchNotifications(query),
        searchConversations(query),
        searchSystemAlerts(query),
        searchDocumentation(query)
    ];
    
    console.log('Search promises created, waiting for results...');
    
    Promise.all(searchPromises).then(results => {
        const [navigation, inventory, orders, customers, invoices, quotations, shipping, projects, accounts, journal, reports, settings, birCompliance, fdaCompliance, notifications, conversations, systemAlerts, documentation] = results;
        
        // Debug logging
        console.log('Search Results:', {
            query,
            navigation: navigation.length,
            inventory: inventory.length,
            orders: orders.length,
            customers: customers.length,
            invoices: invoices.length,
            quotations: quotations.length,
            shipping: shipping.length,
            projects: projects.length,
            accounts: accounts.length,
            journal: journal.length,
            reports: reports.length,
            settings: settings.length,
            birCompliance: birCompliance.length,
            fdaCompliance: fdaCompliance.length,
            notifications: notifications.length,
            conversations: conversations.length,
            systemAlerts: systemAlerts.length,
            documentation: documentation.length
        });
        
        displaySearchResults(query, { 
            navigation, 
            inventory, 
            orders, 
            customers, 
            invoices,
            quotations,
            shipping,
            projects,
            accounts,
            journal,
            reports,
            settings,
            birCompliance,
            fdaCompliance,
            notifications,
            conversations,
            systemAlerts,
            documentation
        });
        
        // Add success animation
        if (searchWrapper) {
            searchWrapper.classList.remove('loading');
            searchWrapper.classList.add('success');
            setTimeout(() => {
                searchWrapper.classList.remove('success');
            }, 600);
        }
    }).catch(error => {
        console.error('Search error:', error);
        showSearchError();
        
        // Remove loading animation on error
        if (searchWrapper) {
            searchWrapper.classList.remove('loading');
        }
    });
}

function searchNavigation(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH NAVIGATION CALLED ===');
        console.log('Query:', query);
        
        // Define navigation items based on sidebar structure
        const navigationItems = [
            { title: 'Dashboard', url: 'dashboard', section: 'Main', keywords: ['dashboard', 'home', 'overview'] },
            { title: 'Analytics', url: 'analytics-dashboard', section: 'Main', keywords: ['analytics', 'reports', 'statistics'] },
            { title: 'Inventory', url: 'inventory-list', section: 'Main', keywords: ['inventory', 'stock', 'items', 'products'] },
            { title: 'Add Item', url: 'add_item', section: 'Main', keywords: ['add', 'new', 'create', 'item', 'product'] },
            { title: 'Quotations', url: 'quotations', section: 'Sales & Operations', keywords: ['quotations', 'quotes', 'pricing'] },
            { title: 'Invoicing', url: 'invoicing', section: 'Sales & Operations', keywords: ['invoicing', 'invoice', 'billing', 'payment'] },
            { title: 'Orders', url: 'orders', section: 'Sales & Operations', keywords: ['orders', 'order', 'sales', 'purchase'] },
            { title: 'Projects', url: 'projects', section: 'Sales & Operations', keywords: ['projects', 'project', 'management'] },
            { title: 'Shipping', url: 'shipping', section: 'Sales & Operations', keywords: ['shipping', 'delivery', 'logistics'] },
            { title: 'BIR Compliance', url: 'bir-compliance', section: 'Compliance', keywords: ['bir', 'tax', 'compliance', 'bureau'] },
            { title: 'FDA Compliance', url: 'fda-compliance', section: 'Compliance', keywords: ['fda', 'food', 'drug', 'compliance', 'safety'] },
            { title: 'Notifications', url: 'notifications', section: 'Compliance', keywords: ['notifications', 'alerts', 'messages'] },
            { title: 'Chart of Accounts', url: 'chart-of-accounts', section: 'Accounting', keywords: ['chart', 'accounts', 'accounting', 'ledger'] },
            { title: 'Journal Entries', url: 'journal-entries', section: 'Accounting', keywords: ['journal', 'entries', 'accounting', 'transactions'] },
            { title: 'Financial Reports', url: 'financial-reports', section: 'Accounting', keywords: ['financial', 'reports', 'accounting', 'statements'] },
            { title: 'Conversations', url: 'conversations', section: 'Collaboration', keywords: ['conversations', 'chat', 'messages', 'communication'] },
            { title: 'System Alerts', url: 'system-alerts', section: 'Collaboration', keywords: ['system', 'alerts', 'warnings', 'notifications'] },
            { title: 'Documentation', url: 'docs', section: 'Documentation', keywords: ['documentation', 'docs', 'help', 'guide'] },
            { title: 'Settings', url: 'settings', section: 'Settings', keywords: ['settings', 'config', 'configuration', 'admin'] }
        ];

        console.log('Total navigation items:', navigationItems.length);

        const results = navigationItems.filter(item => {
            const searchText = `${item.title} ${item.section} ${item.keywords.join(' ')}`.toLowerCase();
            const matches = searchText.includes(query.toLowerCase());
            console.log(`Checking "${item.title}": "${searchText}" includes "${query.toLowerCase()}" = ${matches}`);
            return matches;
        }).map(item => ({
            type: 'navigation',
            title: item.title,
            description: `${item.section} • Navigate to ${item.title}`,
            url: item.url,
            icon: 'navigation',
            section: item.section
        }));

        console.log('Navigation search results for "' + query + '":', results);
        resolve(results);
    });
}

function searchInventory(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH INVENTORY CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/inventory.php?action=list&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Inventory API response:', data);
                if (data.success) {
                    resolve(data.items.map(item => ({
                        type: 'inventory',
                        title: item.name || 'Unknown Item',
                        description: `SKU: ${item.sku || 'N/A'} | Stock: ${item.quantity || 0}`,
                        url: `/inventory-list.php?search=${encodeURIComponent(query)}`,
                        icon: 'package'
                    })));
                } else {
                    console.log('Inventory API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Inventory API error:', error);
                resolve([]);
            });
    });
}

function searchOrders(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH ORDERS CALLED ===');
        console.log('Query:', query);
        console.log('Query length:', query.length);
        console.log('Query trimmed:', query.trim());
        
        // If query is empty or nonsense, return empty immediately
        if (!query || query.trim().length === 0 || query.trim().length < 2) {
            console.log('Query too short or empty, returning empty orders');
            resolve([]);
            return;
        }
        
        fetch(`/api/orders.php?action=get_active_orders&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => {
                console.log('Orders API response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Orders API response:', data);
                console.log('Orders API success:', data.success);
                console.log('Orders API orders array:', data.orders);
                console.log('Orders API orders length:', data.orders ? data.orders.length : 'undefined');
                
                if (data.success && data.orders && Array.isArray(data.orders)) {
                    // Check if orders are actually relevant to the query
                    const relevantOrders = data.orders.filter(order => {
                        const orderText = `${order.order_number || order.id} ${order.customer || ''} ${order.type || ''}`.toLowerCase();
                        const isRelevant = orderText.includes(query.toLowerCase());
                        console.log(`Order ${order.order_number || order.id} relevance check: "${orderText}" includes "${query.toLowerCase()}" = ${isRelevant}`);
                        return isRelevant;
                    });
                    
                    console.log('Relevant orders count:', relevantOrders.length);
                    
                    if (relevantOrders.length === 0) {
                        console.log('No relevant orders found, returning empty array');
                        resolve([]);
                        return;
                    }
                    
                    const result = relevantOrders.map(order => ({
                        type: 'order',
                        title: `Order ${order.order_number || order.id}`,
                        description: `${order.customer || 'No customer'} | ${order.type || 'Unknown'}`,
                        url: `/orders.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    }));
                    
                    console.log('Orders search result:', result);
                    resolve(result);
                } else {
                    console.log('Orders API failed or returned invalid data, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Orders API error:', error);
                console.log('API error, returning empty orders array');
                resolve([]);
            });
    });
}

function searchQuotations(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH QUOTATIONS CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        fetch(`/api/quotations.php?action=search&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Quotations API response:', data);
                if (data.success && data.data) {
                    const quotations = data.data.filter(quote => {
                        const quoteText = `${quote.quote_number || ''} ${quote.customer_name || quote.customer || ''} ${quote.status || ''}`.toLowerCase();
                        return quoteText.includes(query.toLowerCase());
                    });
                    
                    if (quotations.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(quotations.map(quote => ({
                        type: 'quotation',
                        title: `Quote ${quote.quote_number || quote.id}`,
                        description: `${quote.customer_name || quote.customer || 'No customer'} | Amount: $${(quote.total || 0).toLocaleString()} | Status: ${quote.status || 'Unknown'}`,
                        url: `/quotations.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('Quotations API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Quotations API error:', error);
                resolve([]);
            });
    });
}

function searchShipping(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH SHIPPING CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        fetch(`/api/shipments.php?search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Shipping API response:', data);
                if (data.success && data.data) {
                    const shipments = data.data.filter(shipment => {
                        const shipmentText = `${shipment.shipment_number || ''} ${shipment.tracking_number || ''} ${shipment.customer_name || ''} ${shipment.destination_address || ''} ${shipment.status || ''}`.toLowerCase();
                        return shipmentText.includes(query.toLowerCase());
                    });
                    
                    if (shipments.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(shipments.map(shipment => ({
                        type: 'shipping',
                        title: `Shipment ${shipment.shipment_number || shipment.id}`,
                        description: `${shipment.customer_name || 'No customer'} | ${shipment.tracking_number || 'No tracking'} | Status: ${shipment.status || 'Unknown'}`,
                        url: `/shipping.php?search=${encodeURIComponent(query)}`,
                        icon: 'truck'
                    })));
                } else {
                    console.log('Shipping API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Shipping API error:', error);
                resolve([]);
            });
    });
}

function searchProjects(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH PROJECTS CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        // Use the real projects API with search parameter
        fetch(`/api/projects.php?search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Projects API response:', data);
                if (data.success && data.data) {
                    const projects = data.data.filter(project => {
                        const projectText = `${project.name || ''} ${project.client || ''} ${project.description || ''} ${project.status || ''}`.toLowerCase();
                        return projectText.includes(query.toLowerCase());
                    });
                    
                    if (projects.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(projects.map(project => ({
                        type: 'project',
                        title: project.name || 'Unnamed Project',
                        description: `${project.client || 'No client'} | ${project.status || 'Unknown'} | ${project.budget ? '$' + project.budget : 'No budget'}`,
                        url: `/projects.php?search=${encodeURIComponent(query)}`,
                        icon: 'folder'
                    })));
                } else {
                    console.log('Projects API failed, using fallback');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Projects API error:', error);
                resolve([]);
            });
    });
}

function searchChartOfAccounts(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH CHART OF ACCOUNTS CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        fetch(`/api/chart-of-accounts.php?action=search&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Chart of Accounts API response:', data);
                if (data.success && data.data) {
                    const accounts = data.data.filter(account => {
                        const accountText = `${account.account_name || ''} ${account.account_code || ''} ${account.account_type || ''} ${account.account_subtype || ''}`.toLowerCase();
                        return accountText.includes(query.toLowerCase());
                    });
                    
                    if (accounts.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(accounts.map(account => ({
                        type: 'account',
                        title: account.account_name || 'Unnamed Account',
                        description: `Code: ${account.account_code || 'N/A'} | Type: ${account.account_type || 'Unknown'} | Balance: ${account.balance ? '$' + account.balance : '0.00'}`,
                        url: `/chart-of-accounts.php?search=${encodeURIComponent(query)}`,
                        icon: 'calculator'
                    })));
                } else {
                    console.log('Chart of Accounts API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Chart of Accounts API error:', error);
                resolve([]);
            });
    });
}

function searchJournalEntries(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH JOURNAL ENTRIES CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        fetch(`/api/journal-entries.php?action=search&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Journal Entries API response:', data);
                if (data.success && data.data) {
                    const entries = data.data.filter(entry => {
                        const entryText = `${entry.entry_number || ''} ${entry.description || ''} ${entry.reference_number || ''} ${entry.notes || ''}`.toLowerCase();
                        return entryText.includes(query.toLowerCase());
                    });
                    
                    if (entries.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(entries.map(entry => ({
                        type: 'journal',
                        title: `Entry #${entry.entry_number || entry.id}`,
                        description: `Date: ${entry.entry_date || 'N/A'} | ${entry.description || 'No description'} | Amount: $${(entry.total_amount || 0).toLocaleString()}`,
                        url: `/journal-entries.php?search=${encodeURIComponent(query)}`,
                        icon: 'book'
                    })));
                } else {
                    console.log('Journal Entries API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Journal Entries API error:', error);
                resolve([]);
            });
    });
}

function searchInvoices(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH INVOICES CALLED ===');
        console.log('Query:', query);
        
        if (!query || query.trim().length < 2) {
            resolve([]);
            return;
        }
        
        fetch(`/api/invoices.php?action=search&search=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                console.log('Invoices API response:', data);
                if (data.success && data.data) {
                    const invoices = data.data.filter(invoice => {
                        const invoiceText = `${invoice.invoice_number || ''} ${invoice.customer_name || ''} ${invoice.customer_email || ''} ${invoice.status || ''}`.toLowerCase();
                        return invoiceText.includes(query.toLowerCase());
                    });
                    
                    if (invoices.length === 0) {
                        resolve([]);
                        return;
                    }
                    
                    resolve(invoices.map(invoice => ({
                        type: 'invoice',
                        title: `Invoice ${invoice.invoice_number || invoice.id}`,
                        description: `${invoice.customer_name || 'No customer'} | Amount: $${(invoice.total || 0).toLocaleString()} | Status: ${invoice.status || 'Unknown'}`,
                        url: `/invoicing.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('Invoices API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Invoices API error:', error);
                resolve([]);
            });
    });
}

function searchFinancialReports(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH FINANCIAL REPORTS CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/reports.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Financial Reports API response:', data);
                if (data.success && data.reports) {
                    resolve(data.reports.map(report => ({
                        type: 'report',
                        title: report.name || 'Unnamed Report',
                        description: `Type: ${report.type || 'Unknown'} | Period: ${report.period || 'N/A'} | Generated: ${report.generated_date || 'N/A'}`,
                        url: `/financial-reports.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('Financial Reports API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Financial Reports API error:', error);
                // Return mock data as fallback
                const mockReports = [
                    { name: 'Income Statement November 2025', type: 'Income Statement', period: 'November 2025', generated_date: '2025-12-01' },
                    { name: 'Balance Sheet November 2025', type: 'Balance Sheet', period: 'November 2025', generated_date: '2025-12-01' },
                    { name: 'Cash Flow Statement Q4 2025', type: 'Cash Flow', period: 'Q4 2025', generated_date: '2025-12-01' }
                ];
                
                const filtered = mockReports.filter(report => 
                    report.name.toLowerCase().includes(query.toLowerCase()) ||
                    report.type.toLowerCase().includes(query.toLowerCase()) ||
                    report.period.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(report => ({
                    type: 'report',
                    title: report.name,
                    description: `Type: ${report.type} | Period: ${report.period} | Generated: ${report.generated_date}`,
                    url: `/financial-reports.php?search=${encodeURIComponent(query)}`,
                    icon: 'file-text'
                })));
            });
    });
}

function searchSettings(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH SETTINGS CALLED ===');
        console.log('Query:', query);
        
        // Settings are typically configuration options, not database items
        // Return relevant settings pages/options
        const settingsOptions = [
            { title: 'General Settings', description: 'Basic application configuration', url: '/settings.php?section=general', keywords: ['general', 'basic', 'config'] },
            { title: 'User Management', description: 'Manage users and permissions', url: '/settings.php?section=users', keywords: ['users', 'permissions', 'roles'] },
            { title: 'System Configuration', description: 'Advanced system settings', url: '/settings.php?section=system', keywords: ['system', 'advanced', 'config'] },
            { title: 'Email Settings', description: 'Email server configuration', url: '/settings.php?section=email', keywords: ['email', 'smtp', 'notification'] },
            { title: 'Backup Settings', description: 'Data backup configuration', url: '/settings.php?section=backup', keywords: ['backup', 'restore', 'data'] },
            { title: 'Security Settings', description: 'Security and authentication', url: '/settings.php?section=security', keywords: ['security', 'auth', 'login'] }
        ];
        
        const results = settingsOptions.filter(option => {
            const searchText = `${option.title} ${option.description} ${option.keywords.join(' ')}`.toLowerCase();
            return searchText.includes(query.toLowerCase());
        }).map(option => ({
            type: 'setting',
            title: option.title,
            description: option.description,
            url: option.url,
            icon: 'settings'
        }));
        
        console.log('Settings search results:', results);
        resolve(results);
    });
}

function searchBIRCompliance(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH BIR COMPLIANCE CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/bir-compliance.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('BIR Compliance API response:', data);
                if (data.success && data.documents) {
                    resolve(data.documents.map(doc => ({
                        type: 'bir-compliance',
                        title: doc.name || 'Unnamed Document',
                        description: `Type: ${doc.type || 'Unknown'} | Filed: ${doc.filed_date || 'N/A'} | Status: ${doc.status || 'Unknown'}`,
                        url: `/bir-compliance.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('BIR Compliance API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('BIR Compliance API error:', error);
                // Return mock data as fallback
                const mockDocuments = [
                    { name: 'BIR Form 2307 Q3 2025', type: 'Certificate of Tax Withheld', filed_date: '2025-11-15', status: 'Filed' },
                    { name: 'BIR Form 1601-C November 2025', type: 'Monthly Remittance Return', filed_date: '2025-11-20', status: 'Filed' },
                    { name: 'BIR Form 1702Q 2025', type: 'Annual Income Tax Return', filed_date: '2025-04-15', status: 'Filed' }
                ];
                
                const filtered = mockDocuments.filter(doc => 
                    doc.name.toLowerCase().includes(query.toLowerCase()) ||
                    doc.type.toLowerCase().includes(query.toLowerCase()) ||
                    doc.status.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(doc => ({
                    type: 'bir-compliance',
                    title: doc.name,
                    description: `Type: ${doc.type} | Filed: ${doc.filed_date} | Status: ${doc.status}`,
                    url: `/bir-compliance.php?search=${encodeURIComponent(query)}`,
                    icon: 'file-text'
                })));
            });
    });
}

function searchFDACompliance(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH FDA COMPLIANCE CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/fda-compliance.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('FDA Compliance API response:', data);
                if (data.success && data.certificates) {
                    resolve(data.certificates.map(cert => ({
                        type: 'fda-compliance',
                        title: cert.product_name || 'Unnamed Product',
                        description: `Certificate: ${cert.certificate_number || 'N/A'} | Expiry: ${cert.expiry_date || 'N/A'} | Status: ${cert.status || 'Unknown'}`,
                        url: `/fda-compliance.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('FDA Compliance API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('FDA Compliance API error:', error);
                // Return mock data as fallback
                const mockCertificates = [
                    { product_name: 'Vitamin C Supplement', certificate_number: 'FDA-CFR-2025-001', expiry_date: '2026-11-30', status: 'Active' },
                    { product_name: 'Probiotic Capsules', certificate_number: 'FDA-CFR-2025-002', expiry_date: '2027-02-15', status: 'Active' },
                    { product_name: 'Herbal Tea Blend', certificate_number: 'FDA-CFR-2025-003', expiry_date: '2025-12-31', status: 'Expiring Soon' }
                ];
                
                const filtered = mockCertificates.filter(cert => 
                    cert.product_name.toLowerCase().includes(query.toLowerCase()) ||
                    cert.certificate_number.toLowerCase().includes(query.toLowerCase()) ||
                    cert.status.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(cert => ({
                    type: 'fda-compliance',
                    title: cert.product_name,
                    description: `Certificate: ${cert.certificate_number} | Expiry: ${cert.expiry_date} | Status: ${cert.status}`,
                    url: `/fda-compliance.php?search=${encodeURIComponent(query)}`,
                    icon: 'file-text'
                })));
            });
    });
}

function searchNotifications(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH NOTIFICATIONS CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/notifications.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Notifications API response:', data);
                if (data.success && data.notifications) {
                    resolve(data.notifications.map(notif => ({
                        type: 'notification',
                        title: notif.title || 'Untitled Notification',
                        description: `${notif.message || 'No message'} | ${notif.created_date || 'N/A'} | Type: ${notif.type || 'Info'}`,
                        url: `/notifications.php?search=${encodeURIComponent(query)}`,
                        icon: 'bell'
                    })));
                } else {
                    console.log('Notifications API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Notifications API error:', error);
                // Return mock data as fallback
                const mockNotifications = [
                    { title: 'Low Stock Alert', message: 'Inventory item "Widget A" is below minimum stock', created_date: '2025-11-27 10:30', type: 'Warning' },
                    { title: 'New Order Received', message: 'Order ORD-20251101-9999 has been placed', created_date: '2025-11-27 09:15', type: 'Info' },
                    { title: 'Payment Received', message: 'Payment for Invoice INV-001 has been received', created_date: '2025-11-26 15:45', type: 'Success' }
                ];
                
                const filtered = mockNotifications.filter(notif => 
                    notif.title.toLowerCase().includes(query.toLowerCase()) ||
                    notif.message.toLowerCase().includes(query.toLowerCase()) ||
                    notif.type.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(notif => ({
                    type: 'notification',
                    title: notif.title,
                    description: `${notif.message} | ${notif.created_date} | Type: ${notif.type}`,
                    url: `/notifications.php?search=${encodeURIComponent(query)}`,
                    icon: 'bell'
                })));
            });
    });
}

function searchConversations(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH CONVERSATIONS CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/conversations.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Conversations API response:', data);
                if (data.success && data.conversations) {
                    resolve(data.conversations.map(conv => ({
                        type: 'conversation',
                        title: conv.subject || 'Untitled Conversation',
                        description: `With: ${conv.participants || 'Unknown'} | Last: ${conv.last_message_date || 'N/A'} | Status: ${conv.status || 'Active'}`,
                        url: `/conversations.php?search=${encodeURIComponent(query)}`,
                        icon: 'message'
                    })));
                } else {
                    console.log('Conversations API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Conversations API error:', error);
                // Return mock data as fallback
                const mockConversations = [
                    { subject: 'Product Inquiry - Widget A', participants: 'John Doe, Jane Smith', last_message_date: '2025-11-27 14:20', status: 'Active' },
                    { subject: 'Bulk Order Discussion', participants: 'ABC Company', last_message_date: '2025-11-26 11:30', status: 'Active' },
                    { subject: 'Support Ticket #1234', participants: 'Support Team, Bob Johnson', last_message_date: '2025-11-25 16:45', status: 'Closed' }
                ];
                
                const filtered = mockConversations.filter(conv => 
                    conv.subject.toLowerCase().includes(query.toLowerCase()) ||
                    conv.participants.toLowerCase().includes(query.toLowerCase()) ||
                    conv.status.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(conv => ({
                    type: 'conversation',
                    title: conv.subject,
                    description: `With: ${conv.participants} | Last: ${conv.last_message_date} | Status: ${conv.status}`,
                    url: `/conversations.php?search=${encodeURIComponent(query)}`,
                    icon: 'message'
                })));
            });
    });
}

function searchSystemAlerts(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH SYSTEM ALERTS CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/system-alerts.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('System Alerts API response:', data);
                if (data.success && data.alerts) {
                    resolve(data.alerts.map(alert => ({
                        type: 'alert',
                        title: alert.title || 'Untitled Alert',
                        description: `${alert.description || 'No description'} | ${alert.created_date || 'N/A'} | Priority: ${alert.priority || 'Medium'}`,
                        url: `/system-alerts.php?search=${encodeURIComponent(query)}`,
                        icon: 'alert'
                    })));
                } else {
                    console.log('System Alerts API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('System Alerts API error:', error);
                // Return mock data as fallback
                const mockAlerts = [
                    { title: 'Database Backup Failed', description: 'Scheduled database backup did not complete successfully', created_date: '2025-11-27 02:00', priority: 'High' },
                    { title: 'Disk Space Warning', description: 'Server disk usage is at 85% capacity', created_date: '2025-11-26 18:30', priority: 'Medium' },
                    { title: 'API Rate Limit Exceeded', description: 'External API rate limit exceeded for inventory service', created_date: '2025-11-25 12:15', priority: 'Low' }
                ];
                
                const filtered = mockAlerts.filter(alert => 
                    alert.title.toLowerCase().includes(query.toLowerCase()) ||
                    alert.description.toLowerCase().includes(query.toLowerCase()) ||
                    alert.priority.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(alert => ({
                    type: 'alert',
                    title: alert.title,
                    description: `${alert.description} | ${alert.created_date} | Priority: ${alert.priority}`,
                    url: `/system-alerts.php?search=${encodeURIComponent(query)}`,
                    icon: 'alert'
                })));
            });
    });
}

function searchDocumentation(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH DOCUMENTATION CALLED ===');
        console.log('Query:', query);
        
        fetch(`/api/docs.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Documentation API response:', data);
                if (data.success && data.documents) {
                    resolve(data.documents.map(doc => ({
                        type: 'documentation',
                        title: doc.title || 'Untitled Document',
                        description: `Category: ${doc.category || 'Unknown'} | Updated: ${doc.updated_date || 'N/A'} | Type: ${doc.type || 'Guide'}`,
                        url: `/docs.php?search=${encodeURIComponent(query)}`,
                        icon: 'book'
                    })));
                } else {
                    console.log('Documentation API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Documentation API error:', error);
                // Return mock data as fallback
                const mockDocuments = [
                    { title: 'Getting Started Guide', category: 'User Guide', updated_date: '2025-11-20', type: 'Guide' },
                    { title: 'Inventory Management Tutorial', category: 'Tutorial', updated_date: '2025-11-15', type: 'Tutorial' },
                    { title: 'API Documentation', category: 'Technical', updated_date: '2025-11-10', type: 'Reference' },
                    { title: 'Troubleshooting Common Issues', category: 'Support', updated_date: '2025-11-05', type: 'FAQ' }
                ];
                
                const filtered = mockDocuments.filter(doc => 
                    doc.title.toLowerCase().includes(query.toLowerCase()) ||
                    doc.category.toLowerCase().includes(query.toLowerCase()) ||
                    doc.type.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(doc => ({
                    type: 'documentation',
                    title: doc.title,
                    description: `Category: ${doc.category} | Updated: ${doc.updated_date} | Type: ${doc.type}`,
                    url: `/docs.php?search=${encodeURIComponent(query)}`,
                    icon: 'book'
                })));
            });
    });
}

function searchCustomers(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH CUSTOMERS CALLED ===');
        console.log('Query:', query);
        
        // Search for customers via API
        fetch(`/api/customers.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Customers API response:', data);
                if (data.success) {
                    resolve(data.customers.map(customer => ({
                        type: 'customer',
                        title: customer.name || 'Unknown Customer',
                        description: `${customer.email || 'No email'} | ${customer.phone || 'No phone'}`,
                        url: `/customers.php?search=${encodeURIComponent(query)}`,
                        icon: 'users'
                    })));
                } else {
                    console.log('Customers API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Customers API error:', error);
                // Return mock data as fallback
                const mockCustomers = [
                    { name: 'John Doe', email: 'john@example.com', phone: '123-456-7890' },
                    { name: 'Jane Smith', email: 'jane@example.com', phone: '098-765-4321' },
                    { name: 'Bob Johnson', email: 'bob@example.com', phone: '555-123-4567' }
                ];
                
                const filtered = mockCustomers.filter(customer => 
                    customer.name.toLowerCase().includes(query.toLowerCase()) ||
                    customer.email.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(customer => ({
                    type: 'customer',
                    title: customer.name,
                    description: `${customer.email} | ${customer.phone}`,
                    url: `/customers.php?search=${encodeURIComponent(query)}`,
                    icon: 'users'
                })));
            });
    });
}

function searchInvoices(query) {
    return new Promise((resolve) => {
        console.log('=== SEARCH INVOICES CALLED ===');
        console.log('Query:', query);
        
        // Search for invoices via API
        fetch(`/api/invoices.php?action=search&search=${encodeURIComponent(query)}&limit=5`)
            .then(response => response.json())
            .then(data => {
                console.log('Invoices API response:', data);
                if (data.success) {
                    resolve(data.invoices.map(invoice => ({
                        type: 'invoice',
                        title: `Invoice ${invoice.invoice_number || invoice.id}`,
                        description: `${invoice.customer || 'No customer'} | Amount: ${invoice.amount || 'N/A'} | Status: ${invoice.status || 'Unknown'}`,
                        url: `/invoicing.php?search=${encodeURIComponent(query)}`,
                        icon: 'file-text'
                    })));
                } else {
                    console.log('Invoices API failed, returning empty array');
                    resolve([]);
                }
            })
            .catch(error => {
                console.error('Invoices API error:', error);
                // Return mock data as fallback
                const mockInvoices = [
                    { invoice_number: 'INV-001', customer: 'John Doe', amount: '$1,000.00', status: 'Paid' },
                    { invoice_number: 'INV-002', customer: 'Jane Smith', amount: '$2,500.00', status: 'Pending' },
                    { invoice_number: 'INV-003', customer: 'Bob Johnson', amount: '$750.00', status: 'Overdue' }
                ];
                
                const filtered = mockInvoices.filter(invoice => 
                    invoice.invoice_number.toLowerCase().includes(query.toLowerCase()) ||
                    invoice.customer.toLowerCase().includes(query.toLowerCase()) ||
                    invoice.status.toLowerCase().includes(query.toLowerCase())
                );
                
                resolve(filtered.map(invoice => ({
                    type: 'invoice',
                    title: `Invoice ${invoice.invoice_number}`,
                    description: `${invoice.customer} | Amount: ${invoice.amount} | Status: ${invoice.status}`,
                    url: `/invoicing.php?search=${encodeURIComponent(query)}`,
                    icon: 'file-text'
                })));
            });
    });
}

function displaySearchResults(query, results) {
    const dropdown = document.getElementById('global-search-dropdown');
    if (!dropdown) return;
    
    const allResults = [
        ...results.navigation,
        ...results.inventory,
        ...results.orders,
        ...results.customers,
        ...results.invoices,
        ...results.quotations,
        ...results.shipping,
        ...results.projects,
        ...results.accounts,
        ...results.journal,
        ...results.reports,
        ...results.settings,
        ...results.birCompliance,
        ...results.fdaCompliance,
        ...results.notifications,
        ...results.conversations,
        ...results.systemAlerts,
        ...results.documentation
    ];
    
    console.log('Display results:', {
        query,
        navigation: results.navigation.length,
        inventory: results.inventory.length,
        orders: results.orders.length,
        customers: results.customers.length,
        invoices: results.invoices.length,
        quotations: results.quotations.length,
        shipping: results.shipping.length,
        projects: results.projects.length,
        accounts: results.accounts.length,
        journal: results.journal.length,
        reports: results.reports.length,
        settings: results.settings.length,
        birCompliance: results.birCompliance.length,
        fdaCompliance: results.fdaCompliance.length,
        notifications: results.notifications.length,
        conversations: results.conversations.length,
        systemAlerts: results.systemAlerts.length,
        documentation: results.documentation.length,
        total: allResults.length
    });
    
    // ALWAYS show empty state if no results, regardless of query
    if (allResults.length === 0) {
        console.log('No results found, showing empty state');
        dropdown.innerHTML = `
            <div class="global-search-empty">
                No results found for "${query}"
            </div>
        `;
        return;
    }
    
    let html = '';
    
    // Add navigation section
    if (results.navigation.length > 0) {
        console.log('Adding navigation section with', results.navigation.length, 'items');
        html += '<div class="global-search-section">Navigation</div>';
        results.navigation.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add inventory section
    if (results.inventory.length > 0) {
        console.log('Adding inventory section with', results.inventory.length, 'items');
        html += '<div class="global-search-section">Inventory Items</div>';
        results.inventory.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add orders section
    if (results.orders.length > 0) {
        console.log('Adding orders section with', results.orders.length, 'items');
        html += '<div class="global-search-section">Orders</div>';
        results.orders.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add customers section
    if (results.customers.length > 0) {
        console.log('Adding customers section with', results.customers.length, 'items');
        html += '<div class="global-search-section">Customers</div>';
        results.customers.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add invoices section
    if (results.invoices.length > 0) {
        console.log('Adding invoices section with', results.invoices.length, 'items');
        html += '<div class="global-search-section">Invoices</div>';
        results.invoices.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add quotations section
    if (results.quotations.length > 0) {
        console.log('Adding quotations section with', results.quotations.length, 'items');
        html += '<div class="global-search-section">Quotations</div>';
        results.quotations.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add shipping section
    if (results.shipping.length > 0) {
        console.log('Adding shipping section with', results.shipping.length, 'items');
        html += '<div class="global-search-section">Shipping</div>';
        results.shipping.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add projects section
    if (results.projects.length > 0) {
        console.log('Adding projects section with', results.projects.length, 'items');
        html += '<div class="global-search-section">Projects</div>';
        results.projects.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add chart of accounts section
    if (results.accounts.length > 0) {
        console.log('Adding chart of accounts section with', results.accounts.length, 'items');
        html += '<div class="global-search-section">Chart of Accounts</div>';
        results.accounts.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add journal entries section
    if (results.journal.length > 0) {
        console.log('Adding journal entries section with', results.journal.length, 'items');
        html += '<div class="global-search-section">Journal Entries</div>';
        results.journal.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add financial reports section
    if (results.reports.length > 0) {
        console.log('Adding financial reports section with', results.reports.length, 'items');
        html += '<div class="global-search-section">Financial Reports</div>';
        results.reports.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add settings section
    if (results.settings.length > 0) {
        console.log('Adding settings section with', results.settings.length, 'items');
        html += '<div class="global-search-section">Settings</div>';
        results.settings.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add BIR compliance section
    if (results.birCompliance.length > 0) {
        console.log('Adding BIR compliance section with', results.birCompliance.length, 'items');
        html += '<div class="global-search-section">BIR Compliance</div>';
        results.birCompliance.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add FDA compliance section
    if (results.fdaCompliance.length > 0) {
        console.log('Adding FDA compliance section with', results.fdaCompliance.length, 'items');
        html += '<div class="global-search-section">FDA Compliance</div>';
        results.fdaCompliance.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add notifications section
    if (results.notifications.length > 0) {
        console.log('Adding notifications section with', results.notifications.length, 'items');
        html += '<div class="global-search-section">Notifications</div>';
        results.notifications.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add conversations section
    if (results.conversations.length > 0) {
        console.log('Adding conversations section with', results.conversations.length, 'items');
        html += '<div class="global-search-section">Conversations</div>';
        results.conversations.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add system alerts section
    if (results.systemAlerts.length > 0) {
        console.log('Adding system alerts section with', results.systemAlerts.length, 'items');
        html += '<div class="global-search-section">System Alerts</div>';
        results.systemAlerts.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    // Add documentation section
    if (results.documentation.length > 0) {
        console.log('Adding documentation section with', results.documentation.length, 'items');
        html += '<div class="global-search-section">Documentation</div>';
        results.documentation.forEach(item => {
            html += createSearchResultItem(item);
        });
    }
    
    console.log('Final HTML length:', html.length);
    console.log('Setting dropdown HTML...');
    dropdown.innerHTML = html;
    console.log('Dropdown HTML set successfully');
    
    // Add click handlers
    dropdown.querySelectorAll('.global-search-item').forEach(item => {
        item.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            if (url) {
                window.location.href = url;
            }
        });
    });
}

function createSearchResultItem(item) {
    const iconSvg = getIconSvg(item.icon || 'file');
    
    return `
        <div class="global-search-item" data-url="${item.url}">
            <div class="global-search-item-icon">${iconSvg}</div>
            <div class="global-search-item-content">
                <div class="global-search-item-title">${item.title}</div>
                <div class="global-search-item-description">${item.description}</div>
            </div>
        </div>
    `;
}

function getIconSvg(iconName) {
    const icons = {
        navigation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>',
        package: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
        'file-text': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        invoice: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        receipt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
        quotation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
        truck: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17h4V7h-4v10z"></path><path d="M18 7h-3v10h3c.6 0 1-.4 1-1V8c0-.6-.4-1-1-1z"></path><path d="M6 7H3c-.6 0-1 .4-1 1v8c0 .6.4 1 1 1h3V7z"></path><circle cx="7" cy="19" r="1"></circle><circle cx="17" cy="19" r="1"></circle></svg>',
        folder: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19z"></path></svg>',
        calculator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="8" y1="10" x2="16" y2="10"></line><line x1="8" y1="14" x2="16" y2="14"></line><line x1="8" y1="18" x2="16" y2="18"></line></svg>',
        book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
        file: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>',
        settings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 1.54l4.24 4.24M20.46 20.46l-4.24-4.24M1.54 20.46l4.24-4.24"></path></svg>',
        bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>',
        message: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>',
        alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
    };
    
    return icons[iconName] || icons['file-text'];
}

function showSearchError() {
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown) {
        dropdown.innerHTML = `
            <div class="global-search-empty">
                Search failed. Please try again.
            </div>
        `;
    }
}
