package ke.co.intellicash.android.data.repository

import ke.co.intellicash.android.data.api.IntelliCashApi
import ke.co.intellicash.android.data.model.*
import retrofit2.Response
import javax.inject.Inject
import javax.inject.Singleton

sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Error(val code: String, val message: String) : ApiResult<Nothing>()
}

@Singleton
class IntelliCashRepository @Inject constructor(
    private val api: IntelliCashApi,
    private val tokenStore: TokenStore
) {
    private suspend fun <T> safeCall(block: suspend () -> Response<ApiEnvelope<T>>): ApiResult<T> {
        return try {
            val response = block()
            if (response.isSuccessful) {
                val body = response.body()
                if (body != null) {
                    ApiResult.Success(body.data)
                } else {
                    ApiResult.Error("EMPTY_BODY", "Empty response from server")
                }
            } else {
                ApiResult.Error("HTTP_${response.code()}", response.message())
            }
        } catch (e: Exception) {
            ApiResult.Error("NETWORK_ERROR", e.message ?: "Connection failed")
        }
    }

    suspend fun login(email: String, password: String): ApiResult<LoginResponse> {
        return safeCall { api.login(LoginRequest(email, password)) }.also { result ->
            if (result is ApiResult.Success) {
                val user = result.data
                // The API uses cookie-based auth; for mobile we extract the Set-Cookie
                // header. For now, store the session info and rely on cookie jar.
                tokenStore.saveSession(
                    token = "",
                    userId = user.id,
                    name = user.name,
                    role = user.role
                )
            }
        }
    }

    suspend fun logout(): ApiResult<Map<String, Boolean>> {
        val result = safeCall { api.logout() }
        tokenStore.clear()
        return result
    }

    suspend fun getCurrentUser(): ApiResult<User> = safeCall { api.me() }

    suspend fun getGroups(): ApiResult<List<Group>> = safeCall { api.getGroups() }

    suspend fun getGroup(id: String): ApiResult<Group> = safeCall { api.getGroup(id) }

    suspend fun getGroupMembers(groupId: String): ApiResult<List<Member>> =
        safeCall { api.getGroupMembers(groupId) }

    suspend fun getGroupMeetings(groupId: String): ApiResult<List<Meeting>> =
        safeCall { api.getGroupMeetings(groupId) }

    suspend fun getMeeting(groupId: String, meetingId: String): ApiResult<Meeting> =
        safeCall { api.getMeeting(groupId, meetingId) }

    suspend fun getMeetingSteps(groupId: String, meetingId: String): ApiResult<List<MeetingStep>> =
        safeCall { api.getMeetingSteps(groupId, meetingId) }

    suspend fun getGroupLedger(groupId: String): ApiResult<List<LedgerEntry>> =
        safeCall { api.getGroupLedger(groupId) }

    suspend fun getGroupFunds(groupId: String): ApiResult<List<FundAccount>> =
        safeCall { api.getGroupFunds(groupId) }

    suspend fun getPortfolioSummary(): ApiResult<PortfolioSummary> =
        safeCall { api.getPortfolioSummary() }

    suspend fun getNotifications(): ApiResult<List<Notification>> =
        safeCall { api.getNotifications() }

    suspend fun markNotificationRead(id: String): ApiResult<Notification> =
        safeCall { api.markNotificationRead(id) }
}
