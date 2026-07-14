import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useFolders } from './FolderContext';
import FolderTree from './FolderTree';
import SystemFolders from './SystemFolders';
import FolderContextMenu from './FolderContextMenu';

const Sidebar = () => {
    const [searchQuery, setSearchQuery] = useState('');
    const [isToolbarActive, setIsToolbarActive] = useState(false);
    const [isSortMenuOpen, setIsSortMenuOpen] = useState(false);
    const {
        folders, createFolder, activeFolderId, setDraftFolderParentId,
        isProcessing, folderToDelete, setFolderToDelete, deleteFolder,
        folderSort, setFolderSort, fileSort, setFileSort,
        displayFolderId, setDisplayFolderId,
        toastMessage
    } = useFolders();
    const sidebarRef = useRef(null);

    // Close sort menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (isSortMenuOpen && !event.target.closest('.mediamatic-toolbar-sort') && !event.target.closest('.mediamatic-sort-dropdown-container')) {
                setIsSortMenuOpen(false);
            }
        };

        if (isSortMenuOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isSortMenuOpen]);

    // Apply CSS var for native UI shifting and save to localStorage
    useEffect(() => {
        document.body.style.setProperty('--mediamatic-sidebar-width', `300px`);
    }, []);

    const handleCreate = () => {
        setDraftFolderParentId(0);
    };

    return (
        <div
            className={`mediamatic-sidebar-wrapper`}
            ref={sidebarRef}
            style={{ position: 'relative' }}
        >
            <div className="mediamatic-sidebar-inner" style={{ display: 'flex' }}>
                {isProcessing && (
                    <div className="mediamatic-global-overlay" style={{
                        position: 'absolute',
                        top: 0, left: 0, right: 0, bottom: 0,
                        backgroundColor: 'rgba(255, 255, 255, 0.7)',
                        zIndex: 9999,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        pointerEvents: 'all'
                    }}>
                        <svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="50" cy="50" fill="none" r="44" strokeWidth="10" stroke="#ccd0d4" />
                            <circle cx="50" cy="50" fill="none" r="44" strokeWidth="10" stroke="#2271b1" strokeDasharray="276" strokeDashoffset="220" strokeLinecap="round" style={{ transformOrigin: 'center', animation: 'mediamatic_spin 1s linear infinite' }} />
                        </svg>
                    </div>
                )}
                {/* Top Bar: Search & Menu or Toolbar */}
                {isToolbarActive ? (
                    <div className="mediamatic-top-bar mediamatic-toolbar-overlay" style={{ justifyContent: 'space-between', padding: '0', backgroundColor: '#f6f7f7' }}>
                        <div style={{ display: 'flex', alignItems: 'center', flex: '1 1 auto', borderRight: '1px solid #dcdcde', padding: '0 10px' }}>
                            <div style={{ position: 'relative' }} className="mediamatic-sort-wrapper">
                                <button className={`mediamatic-toolbar-btn mediamatic-toolbar-sort ${isSortMenuOpen ? 'active' : ''}`} title={__('Sort Folders', 'mediamatic')} onClick={() => setIsSortMenuOpen(!isSortMenuOpen)} style={{ width: '32px', height: '32px', border: '1px solid #8c8f94', borderRadius: '3px', backgroundColor: isSortMenuOpen ? '#f0f0f1' : '#fff', padding: '0', display: 'flex', justifyContent: 'center', alignItems: 'center', flex: '0 0 auto' }}>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#50575e" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M4 9l3-6 3 6" />
                                        <path d="M5 7h4" />
                                        <path d="M4 14h6l-6 6h6" />
                                        <path d="M17 4v16" />
                                        <path d="M13 16l4 4 4-4" />
                                    </svg>
                                </button>
                                {isSortMenuOpen && (
                                    <div className="mediamatic-sort-dropdown-container">
                                        <ul className="mediamatic-dropdown-list">
                                            <li className="mediamatic-dropdown-item mediamatic-has-submenu">
                                                <div className="mediamatic-item-content">
                                                    <span>{__('Sort Folders', 'mediamatic')}</span>
                                                    <svg className="mediamatic-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                                </div>
                                                <ul className="mediamatic-submenu mediamatic-dropdown-list">
                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFolderSort({ key: 'name', order: 'asc' }); setIsSortMenuOpen(false); }}>
                                                        <div className={`mediamatic-item-content mediamatic-check-item ${folderSort.order === 'asc' ? 'active' : ''}`}>
                                                            {folderSort.order === 'asc' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                            <span>{__('Ascending', 'mediamatic')}</span>
                                                        </div>
                                                    </li>
                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFolderSort({ key: 'name', order: 'desc' }); setIsSortMenuOpen(false); }}>
                                                        <div className={`mediamatic-item-content mediamatic-check-item ${folderSort.order === 'desc' ? 'active' : ''}`}>
                                                            {folderSort.order === 'desc' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                            <span>{__('Descending', 'mediamatic')}</span>
                                                        </div>
                                                    </li>
                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFolderSort({ key: 'default', order: 'default' }); setIsSortMenuOpen(false); }}>
                                                        <div className={`mediamatic-item-content mediamatic-check-item ${folderSort.order === 'default' ? 'active' : ''}`}>
                                                            {folderSort.order === 'default' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                            <span>{__('Default', 'mediamatic')}</span>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </li>
                                            <li className="mediamatic-dropdown-item mediamatic-has-submenu">
                                                <div className="mediamatic-item-content">
                                                    <span>{__('Sort Files', 'mediamatic')}</span>
                                                    <svg className="mediamatic-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                                </div>
                                                <ul className="mediamatic-submenu mediamatic-dropdown-list">

                                                    {['Title', 'Date', 'Modified', 'Author', 'Size'].map(type => {
                                                        const keyLower = type.toLowerCase();
                                                        return (
                                                            <li className="mediamatic-dropdown-item mediamatic-has-submenu" key={keyLower}>
                                                                <div className={`mediamatic-item-content ${fileSort.key === keyLower ? 'active' : ''}`}>
                                                                    <span>{__(`By ${type}`, 'mediamatic')}</span>
                                                                    <svg className="mediamatic-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M9 18l6-6-6-6" /></svg>
                                                                </div>
                                                                <ul className="mediamatic-submenu mediamatic-dropdown-list">
                                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFileSort({ key: keyLower, order: 'asc' }); setIsSortMenuOpen(false); }}>
                                                                        <div className={`mediamatic-item-content mediamatic-check-item ${fileSort.key === keyLower && fileSort.order === 'asc' ? 'active' : ''}`}>
                                                                            {fileSort.key === keyLower && fileSort.order === 'asc' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                                            <span>{__('Ascending', 'mediamatic')}</span>
                                                                        </div>
                                                                    </li>
                                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFileSort({ key: keyLower, order: 'desc' }); setIsSortMenuOpen(false); }}>
                                                                        <div className={`mediamatic-item-content mediamatic-check-item ${fileSort.key === keyLower && fileSort.order === 'desc' ? 'active' : ''}`}>
                                                                            {fileSort.key === keyLower && fileSort.order === 'desc' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                                            <span>{__('Descending', 'mediamatic')}</span>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </li>
                                                        );
                                                    })}

                                                    <li className="mediamatic-dropdown-item mediamatic-divider"></li>
                                                    <li className="mediamatic-dropdown-item" onClick={() => { setFileSort({ key: 'default', order: 'default' }); setIsSortMenuOpen(false); }}>
                                                        <div className={`mediamatic-item-content mediamatic-check-item ${fileSort.key === 'default' ? 'active' : ''}`}>
                                                            {fileSort.key === 'default' ? <svg className="mediamatic-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> : <span className="mediamatic-no-icon"></span>}
                                                            <span>{__('Default', 'mediamatic')}</span>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </li>

                                        </ul>
                                    </div>
                                )}
                            </div>
                        </div>
                        <button className="mediamatic-toolbar-btn mediamatic-toolbar-close" onClick={() => setIsToolbarActive(false)} title={__('Close', 'mediamatic')} style={{ flex: '0 0 auto', width: '50px', backgroundColor: 'transparent', padding: '0', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="#50575e" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L22 20H2Z" />
                            </svg>
                        </button>
                    </div>
                ) : (
                    <div className="mediamatic-top-bar">
                        <div className="mediamatic-search">
                            <div className="mediamatic-search-input-wrapper">
                                <input
                                    type="text"
                                    placeholder={__('Search folders', 'mediamatic')}
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                                <span
                                    className="search-icon"
                                    style={{ cursor: searchQuery ? 'pointer' : 'default', pointerEvents: searchQuery ? 'auto' : 'none' }}
                                    onClick={() => {
                                        if (searchQuery) setSearchQuery('');
                                    }}
                                >
                                    {searchQuery ? (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    ) : (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    )}
                                </span>
                            </div>
                        </div>
                        <button className="mediamatic-hamburger-btn" onClick={() => setIsToolbarActive(true)}>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8c8f94" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                )}

                {/* System Folders */}
                <SystemFolders />

                {/* Tree */}
                <FolderTree searchQuery={searchQuery} />

                {/* Bottom Bar */}
                {folders && folders.length > 0 && (
                    <div className="mediamatic-bottom-bar" style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        <div style={{ display: 'none', flexDirection: 'column', gap: '4px' }}>
                            {/* 
                            <a href="https://mediamatic.oppoyo.com/" target="_blank" rel="noopener noreferrer" style={{ textAlign: 'center', fontSize: '13px', color: '#2271b1', textDecoration: 'none', fontWeight: '500' }}>
                                {__('Upgrade to Pro', 'mediamatic')}
                            </a>
                            */}
                        </div>
                        <button className="button button-primary mediamatic-add-folder-btn" onClick={handleCreate}>
                            + {__('Add Folder', 'mediamatic')}
                        </button>
                    </div>
                )}
            </div>

            {/* Global Context Menu */}
            <FolderContextMenu />

            {/* Custom Delete Confirmation Modal */}
            {folderToDelete && (
                <div style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    background: 'rgba(0, 0, 0, 0.5)', zIndex: 9999999,
                    display: 'flex', alignItems: 'center', justifyContent: 'center'
                }}>
                    <div style={{
                        background: '#fff', borderRadius: '4px', width: '400px',
                        boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                        display: 'flex', flexDirection: 'column', overflow: 'hidden'
                    }}>
                        <div style={{ padding: '15px 20px', borderBottom: '1px solid #dcdcde', background: '#f6f7f7' }}>
                            <h2 style={{ margin: 0, fontSize: '14px', fontWeight: 600, color: '#1d2327' }}>Confirm Delete</h2>
                        </div>
                        <div style={{ padding: '20px', fontSize: '14px', color: '#3c434a' }}>
                            <p style={{ margin: 0 }}>Are you sure you want to delete <strong>"{folderToDelete.name}"</strong>?</p>
                            <p style={{ margin: '10px 0 0 0', color: '#d63638', fontSize: '13px' }}>Note: This will also delete all subfolders inside it. Media files will not be deleted but will be moved back to "Uncategorized".</p>
                        </div>
                        <div style={{ padding: '15px 20px', borderTop: '1px solid #dcdcde', background: '#f6f7f7', display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                            <button
                                className="button"
                                onClick={(e) => { e.stopPropagation(); setFolderToDelete(null); }}
                            >
                                Cancel
                            </button>
                            <button
                                className="button button-primary"
                                style={{ background: '#d63638', borderColor: '#d63638', color: '#fff' }}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    deleteFolder(folderToDelete.id);
                                    setFolderToDelete(null);
                                }}
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            )}



            {/* Custom Minimal Toast Notification */}
            {toastMessage && (
                <div style={{
                    position: 'absolute',
                    bottom: '60px',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    background: '#fff',
                    color: '#3c434a',
                    border: '1px solid #dcdcde',
                    borderLeft: `4px solid ${toastMessage.type === 'success' ? '#46b450' : '#d63638'}`,
                    padding: '10px 16px',
                    borderRadius: '4px',
                    fontSize: '13px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                    zIndex: 999999,
                    pointerEvents: 'none',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px',
                    whiteSpace: 'nowrap',
                    animation: 'mediamatic_fade_in_up 0.2s ease-out'
                }}>
                    {toastMessage.type === 'success' ? (
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#46b450" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    ) : (
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d63638" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    )}
                    <span style={{ fontWeight: 500 }}>{toastMessage.message}</span>
                </div>
            )}

        </div>
    );
};

export default Sidebar;
