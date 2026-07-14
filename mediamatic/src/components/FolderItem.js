import { useRef, useEffect } from '@wordpress/element';
import { useFolders } from './FolderContext';
import FolderInput from './FolderInput';

const FolderItem = ({ folder, index, isSearchResult = false, parentPath = '' }) => {
    const {
        activeFolderId, setActiveFolderId, counts, moveMediaToFolder, createFolder, renameFolder,
        editingFolderId, setEditingFolderId,
        setContextMenu, contextMenu,
        isBulkSelectMode, selectedFolders, setSelectedFolders,
        showToast
    } = useFolders();

    const isEditing = editingFolderId === folder.id;
    const isActive = activeFolderId === folder.id;
    const count = counts[folder.id] || 0;

    const folderRef = useRef(null);

    const selectFolder = (e) => {
        e.stopPropagation();
        setActiveFolderId(folder.id);
    };

    const handleContextMenu = (e) => {
        if (typeof mediamaticParams !== 'undefined' && !mediamaticParams.canEdit) return;
        if (isBulkSelectMode) return;

        e.preventDefault();
        e.stopPropagation();
        setContextMenu({
            visible: true,
            x: e.clientX,
            y: e.clientY,
            folder: folder
        });
    };

    const saveRename = async (newName) => {
        await renameFolder(folder.id, newName);
        setEditingFolderId(null);
    };

    useEffect(() => {
        if (!folderRef.current || typeof jQuery === 'undefined' || !jQuery.ui || !jQuery.ui.droppable) return;

        const $el = jQuery(folderRef.current);

        $el.droppable({
            accept: '.mediamatic-draggable',
            hoverClass: 'mediamatic-droppable-hover',
            tolerance: 'pointer',
            greedy: true, // Prevent event bubbling to parent folders
            drop: function (event, ui) {
                const $draggable = ui.draggable;
                let draggedIds = [];

                if ($draggable.hasClass('attachment')) {
                    // Grid View
                    if ($draggable.hasClass('selected') && jQuery('.attachment.selected').length > 0) {
                        jQuery('.attachment.selected').each(function () {
                            draggedIds.push(jQuery(this).data('id'));
                        });
                    } else {
                        draggedIds.push($draggable.data('id'));
                    }
                } else if ($draggable.is('tr')) {
                    // List View
                    if ($draggable.find('input[type="checkbox"]').prop('checked') && jQuery('.wp-list-table tbody tr input[type="checkbox"]:checked').length > 0) {
                        jQuery('.wp-list-table tbody tr input[type="checkbox"]:checked').each(function () {
                            draggedIds.push(jQuery(this).val());
                        });
                    } else {
                        draggedIds.push($draggable.find('input[type="checkbox"]').val());
                    }
                }

                if (draggedIds.length > 0) {
                    moveMediaToFolder(draggedIds, folder.id).then((success) => {
                        if (success) {
                            showToast(`Successfully moved`, 'success');
                            // Provide a little UI feedback
                            $el.find('>.mediamatic-folder-header').css('background', '#dcf0da');
                            setTimeout(() => {
                                $el.find('>.mediamatic-folder-header').css('background', '');
                            }, 500);
                        } else {
                            showToast(`Failed to move item(s) to "${folder.name}"`, 'error');
                        }
                    });
                }
            }
        });

        return () => {
            try {
                if ($el.hasClass('ui-droppable')) {
                    $el.droppable('destroy');
                }
            } catch (e) {
                console.error('Mediamatic droppable destroy error:', e);
            }
        };
    }, [folder.id, folder.name, moveMediaToFolder]);

    const isContextMenuActive = contextMenu && contextMenu.visible && contextMenu.folder?.id === folder.id;

    return (
        <li className={`mediamatic-folder-item ${isActive ? 'active' : ''} ${isContextMenuActive ? 'context-menu-active' : ''}`} ref={folderRef}>
            {isEditing ? (
                <FolderInput
                    standalone={false}
                    initialName={folder.name}
                    onSave={saveRename}
                    onCancel={() => setEditingFolderId(null)}
                />
            ) : (
                <div
                    className="mediamatic-folder-header"
                    onClick={selectFolder}
                    onContextMenu={handleContextMenu}
                    style={{ display: 'flex', alignItems: 'center', cursor: 'pointer' }}
                >
                    {isBulkSelectMode && (
                        <input
                            type="checkbox"
                            checked={selectedFolders.includes(folder.id)}
                            onChange={(e) => {
                                e.stopPropagation();
                                if (e.target.checked) {
                                    setSelectedFolders([...selectedFolders, folder.id]);
                                } else {
                                    setSelectedFolders(selectedFolders.filter(id => id !== folder.id));
                                }
                            }}
                            onClick={(e) => e.stopPropagation()}
                            style={{
                                margin: '0 8px 0 0',
                                cursor: 'pointer',
                                width: '16px',
                                height: '16px',
                                flexShrink: 0
                            }}
                        />
                    )}
                    <span
                        className={`folder-icon folder-leaf-icon`}
                        style={{
                            marginRight: '5px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: isActive ? '#2271b1' : '#a0aab2',
                            width: '22px',
                            height: '22px',
                            position: 'relative',
                            zIndex: 3
                        }}
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 4H4C2.9 4 2.01 4.9 2.01 6L2 18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V8C22 6.9 21.1 6 20 6H12L10 4Z" />
                        </svg>
                    </span>

                    <span className="folder-name" style={{
                        color: isActive ? '#2271b1' : '#50575e',
                        fontWeight: isActive ? '500' : '400',
                        fontSize: '13px',
                        display: 'flex',
                        flexDirection: 'column',
                        lineHeight: '1.3',
                        minWidth: 0,
                    }}>
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', display: 'flex', alignItems: 'center' }}>
                            {folder.name}
                        </span>
                        {isSearchResult && parentPath && (
                            <span style={{
                                fontSize: '10px',
                                fontWeight: '400',
                                color: '#9ca3af',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                                marginTop: '1px',
                                letterSpacing: '0.1px',
                            }}>
                                {parentPath}
                            </span>
                        )}
                    </span>

                    <span className="folder-count" style={{ marginLeft: 'auto' }}>
                        {count}
                    </span>
                </div>
            )}


        </li>
    );
};

export default FolderItem;
