import { createBrowserRouter } from 'react-router-dom';
import { App } from './App';
import { LoginPage } from '../features/auth/LoginPage';
import { RequireAuth } from '../features/auth/RequireAuth';
import { UsersPage } from '../features/users/UsersPage';
import { MasterDataPage } from '../features/master/MasterDataPage';

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/',
    element: (
      <RequireAuth>
        <App />
      </RequireAuth>
    ),
  },
  {
    path: '/users',
    element: (
      <RequireAuth>
        <UsersPage />
      </RequireAuth>
    ),
  },
  {
    path: '/master-data',
    element: (
      <RequireAuth>
        <MasterDataPage />
      </RequireAuth>
    ),
  },
]);