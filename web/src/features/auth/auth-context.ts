import { createContext } from 'react';

export type User = { id: number; name: string; email: string };
export type School = { id: number; name: string; is_active?: boolean };

export type AuthContextType = {
  token: string | null;
  user: User | null;
  roles: string[];
  activeSchool: School | null;
  schools: School[];
  login: (token: string, user: User, activeSchool: School | null, schools: School[]) => void;
  logout: () => void;
  setContextData: (data: { roles: string[]; activeSchool: School | null; schools: School[] }) => void;
};

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
