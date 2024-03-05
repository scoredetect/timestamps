import { useSelect } from '@wordpress/data';
import { useRef, useState, useEffect } from '@wordpress/element';
import { createClient } from '@supabase/supabase-js';
import { PUBLIC_SUPABASE_ANON_KEY, PUBLIC_SUPABASE_URL } from './constants';

/**
 * Create a single Supabase client for interacting with the database.
 *
 * This function creates a Supabase client using the public Supabase URL and
 * anonymous key. The client is configured to not persist sessions.
 */
const supabaseClient = createClient(PUBLIC_SUPABASE_URL, PUBLIC_SUPABASE_ANON_KEY, {
	auth: {
		persistSession: false,
	},
});

/**
 * Convert a string to kebab-case.
 *
 * This function takes a string and converts it to kebab-case. It replaces
 * uppercase letters with lowercase, spaces and underscores with hyphens,
 * and removes special characters like '@'.
 *
 * @param {string} string - The string to convert.
 * @returns {string} - The converted string in kebab-case.
 */
export const toKebabCase = (string) => {
	return (
		string
			// Replace uppercase letters with lowercase preceded by a hyphen
			.replace(/([a-z])([A-Z])/g, '$1-$2')
			// Replace spaces and underscores with hyphens
			.replace(/[\s_]+/g, '-')
			// Replace periods with hyphens
			.replace(/[\s.]+/g, '-')
			// Remove '@' symbols
			.replace(/@/g, '-')
			// Convert all characters to lowercase
			.toLowerCase()
	);
};

/**
 * Authenticate a session with Supabase.
 *
 * This function takes a session object and uses it to authenticate a session
 * with Supabase. The session object should have 'access_token' and 'refresh_token'
 * properties.
 *
 * @param {object} session - The session to authenticate.
 * @throws {Error} Will throw an error if the authentication fails.
 */
export const authenticateSession = async (session) => {
	try {
		await supabaseClient.auth.setSession({
			access_token: session.access_token,
			refresh_token: session.refresh_token,
		});
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Error authenticating session:', error);
		throw error;
	}
};

/**
 * Get the current user from Supabase.
 *
 * This function retrieves the current user from Supabase. If the user is not
 * logged in or there's no user data, it returns null.
 *
 * @returns {object | null} - The user data or null if no user is logged in.
 */
export const getUser = async () => {
	try {
		const user = await supabaseClient.auth.getUser();
		return user?.data?.user ?? null;
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Error getting user:', error);
		return null;
	}
};

/**
 * Custom React hook to detect when a post has finished saving.
 *
 * This hook uses the `useState` and `useEffect` hooks from React, and the `useSelect` hook from WordPress to monitor the saving state of a post. It returns `true` if the post has finished saving, and `false` otherwise.
 *
 * @returns {boolean} - `true` if the post is done saving, `false` otherwise.
 */
export const useAfterSave = () => {
	// State to track if the post has been saved
	const [isPostSaved, setIsPostSaved] = useState(false);

	// Ref to track if the post saving is in progress
	const isPostSavingInProgress = useRef(false);

	// Use the `useSelect` hook from WordPress to get the saving and autosaving state of the post
	const { isSavingPost, isAutosavingPost } = useSelect((select) => {
		return {
			isSavingPost: select('core/editor').isSavingPost(),
			isAutosavingPost: select('core/editor').isAutosavingPost(),
		};
	});

	// Use the `useEffect` hook from React to update the `isPostSaved` state and `isPostSavingInProgress` ref when the saving state changes
	useEffect(() => {
		if ((isSavingPost || isAutosavingPost) && !isPostSavingInProgress.current) {
			setIsPostSaved(false);
			isPostSavingInProgress.current = true;
		}
		if (!(isSavingPost || isAutosavingPost) && isPostSavingInProgress.current) {
			setIsPostSaved(true);
			isPostSavingInProgress.current = false;
		}
	}, [isSavingPost, isAutosavingPost]);

	return isPostSaved;
};
/**
 * Get the username for a given user.
 *
 * This function takes a user object and returns the username for that user. It first checks if the user has a username in the 'users' table in Supabase. If not, it checks if the user has an email, a preferred username, or a full name. If none of these are available, it returns 'anonymous'.
 *
 * @param {object} user - The user object.
 * @returns {string} - The username for the user.
 * @throws {Error} - Will throw an error if there's an error retrieving the user from Supabase.
 */
export const getUsername = async (user) => {
	try {
		// If there is no userId, return 'anonymous'
		if (!user?.id) {
			return 'anonymous';
		}

		// Get the user from Supabase
		const { data, error } = await supabaseClient
			.from('users')
			.select('username')
			.eq('id', user.id)
			.single();

		// If there's an error, throw it
		if (error) {
			throw new Error(error);
		}

		// If the user has a username, return it
		if (data?.username !== '' && data?.username !== null) {
			return data?.username;
		}

		// If the user has an email, return it
		if (user?.email) {
			return user?.email;
		}

		// If the user has a preferred username, return it in kebab-case
		if (user?.user_metadata?.preferred_username) {
			return toKebabCase(user.user_metadata.preferred_username);
		}

		// If the user has a full name, return it in kebab-case
		if (user?.user_metadata?.full_name) {
			return toKebabCase(user?.user_metadata?.full_name);
		}

		// If all else fails, return 'anonymous'
		return 'anonymous';
	} catch (error) {
		// eslint-disable-next-line no-console
		console.error('Error getting username:', error);
		throw error;
	}
};
