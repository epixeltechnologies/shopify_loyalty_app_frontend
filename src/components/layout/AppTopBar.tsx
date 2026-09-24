import { TopBar } from '@shopify/polaris';
import { useState, useCallback } from 'react';

export function AppTopBar() {
  const [userMenuActive, setUserMenuActive] = useState(false);
  const toggleUserMenu = useCallback(() => setUserMenuActive((active) => !active), []);

  const userMenuMarkup = (
    <TopBar.UserMenu
      actions={[{ items: [{ content: 'Settings', url: '/settings' }] }]}
      name="Shop Admin"
      initials="SA"
      open={userMenuActive}
      onToggle={toggleUserMenu}
    />
  );

  return <TopBar userMenu={userMenuMarkup} />;
}
