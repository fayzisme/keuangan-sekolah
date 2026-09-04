import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from './useAuth';

export function RequireAuth({ children }: { children: React.ReactNode }) {
  const { token } = useAuth();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}
