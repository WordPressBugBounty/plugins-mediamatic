import { useEffect, useRef } from '@wordpress/element';
import { useFolders } from './FolderContext';

const FolderContextMenu = () => {
    const {
        contextMenu, setContextMenu,
        setEditingFolderId,
        setFolderToDelete
    } = useFolders();

    const menuRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) {
                setContextMenu(null);
            }
        };
        const handleScroll = () => setContextMenu(null);

        if (contextMenu && contextMenu.visible) {
            document.addEventListener('mousedown', handleClickOutside);
            window.addEventListener('scroll', handleScroll, true);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            window.removeEventListener('scroll', handleScroll, true);
        };
    }, [contextMenu, setContextMenu]);

    if (!contextMenu || !contextMenu.visible) return null;

    const { x, y, folder } = contextMenu;

    // Estimate dimensions
    const estimatedHeight = 310;
    const estimatedWidth = 160;
    const flyoutWidth = 220;

    let adjustedY = y;
    if (y + estimatedHeight > window.innerHeight) {
        adjustedY = Math.max(10, window.innerHeight - estimatedHeight - 10);
    }

    let adjustedX = x;
    if (x + estimatedWidth > window.innerWidth) {
        adjustedX = Math.max(10, window.innerWidth - estimatedWidth - 10);
    }

    const flyoutSide = (adjustedX + estimatedWidth + flyoutWidth > window.innerWidth) ? 'left' : 'right';

    const handleAction = (action) => {
        if (action === 'rename') {
            setContextMenu(null);
            setEditingFolderId(folder.id);
        } else if (action === 'delete') {
            setContextMenu(null);
            setFolderToDelete(folder);
        }
    };

    return (
        <div
            ref={menuRef}
            className="mediamatic-context-menu"
            style={{
                position: 'fixed',
                top: adjustedY,
                left: adjustedX,
                background: '#fff',
                border: '1px solid #dcdcde',
                boxShadow: '0 2px 8px rgba(0,0,0,0.08)',
                zIndex: 999999,
                padding: '5px 0',
                minWidth: '160px',
                borderRadius: '3px'
            }}
        >
            <div
                className="mediamatic-context-item"
                onClick={() => handleAction('rename')}
                onMouseEnter={e => e.currentTarget.style.background = '#f0f6fc'}
                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                style={{ padding: '7px 15px', cursor: 'pointer', display: 'flex', alignItems: 'center', fontSize: '13px', color: '#50575e' }}
            >
                <span className="dashicons dashicons-edit" style={{ marginRight: '8px', fontSize: '16px', width: '16px', color: '#a0aab2' }}></span>
                Rename
            </div>

            {(typeof window.mediamaticParams === 'undefined' || !!window.mediamaticParams.canDelete) && (
                <>
                    <div style={{ height: '1px', background: '#f0f0f1', margin: '4px 0' }}></div>
                    <div
                        className="mediamatic-context-item"
                        onClick={() => handleAction('delete')}
                        onMouseEnter={e => e.currentTarget.style.background = '#fcf0f1'}
                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                        style={{ padding: '7px 15px', cursor: 'pointer', display: 'flex', alignItems: 'center', fontSize: '13px', color: '#d63638' }}
                    >
                        <span className="dashicons dashicons-trash" style={{ marginRight: '8px', fontSize: '16px', width: '16px', color: '#d63638' }}></span>
                        Delete
                    </div>
                </>
            )}
        </div>
    );
};

export default FolderContextMenu;
