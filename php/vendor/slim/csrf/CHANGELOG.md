# Change Log

## 2016-08-14

Now supports "persistence mode", to persist a single CSRF name/value pair throughout the life of a user's session.  Added the following methods:

- `protected getLastKeyPair` - gets the most recently generated key/value pair from storage.
- `protected loadLastKeyPair` - gets the most recently generated key/value pair from storage, and assign it to `$this->keyPair`.
- `public setPersistentTokenMode`
- `public getPersistentTokenMode`

Note that if CSRF token validation fails, then the token should be renewed regardless of the persistence setting.

The methods `getTokenName` and `getTokenValue` now return `null` if `$this->keyPair` has not yet been set.

### Tests added:

- `testPersistenceModeTrueBetweenRequestsArray` - Token should persist between requests after initial creation, when stored in an array.
- `testPersistenceModeTrueBetweenRequestsArrayAccess` - Token should persist between requests after initial creation, when stored in an ArrayObject.
- `testPersistenceModeFalseBetweenRequestsArray` - Token should be changed between requests, when stored in an array.
- `testPersistenceModeFalseBetweenRequestsArrayAccess` - Token should be changed between requests, when stored in an ArrayObject.
- `testUpdateAfterInvalidTokenWithPersistenceModeTrue` - New token should be generated after an invalid request, even if persistence mode is enabled.