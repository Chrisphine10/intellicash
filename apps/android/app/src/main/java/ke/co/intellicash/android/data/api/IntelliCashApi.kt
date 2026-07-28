package ke.co.intellicash.android.data.api

import ke.co.intellicash.android.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface IntelliCashApi {

    // Auth
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<ApiEnvelope<LoginResponse>>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiEnvelope<Map<String, Boolean>>>

    @GET("auth/me")
    suspend fun me(): Response<ApiEnvelope<User>>

    // Groups
    @GET("groups")
    suspend fun getGroups(): Response<ApiEnvelope<List<Group>>>

    @GET("groups/{id}")
    suspend fun getGroup(@Path("id") id: String): Response<ApiEnvelope<Group>>

    @GET("groups/{id}/members")
    suspend fun getGroupMembers(@Path("id") groupId: String): Response<ApiEnvelope<List<Member>>>

    @GET("groups/{id}/meetings")
    suspend fun getGroupMeetings(@Path("id") groupId: String): Response<ApiEnvelope<List<Meeting>>>

    @GET("groups/{groupId}/meetings/{meetingId}")
    suspend fun getMeeting(
        @Path("groupId") groupId: String,
        @Path("meetingId") meetingId: String
    ): Response<ApiEnvelope<Meeting>>

    @GET("groups/{groupId}/meetings/{meetingId}/steps")
    suspend fun getMeetingSteps(
        @Path("groupId") groupId: String,
        @Path("meetingId") meetingId: String
    ): Response<ApiEnvelope<List<MeetingStep>>>

    @GET("groups/{groupId}/meetings/{meetingId}/attendance")
    suspend fun getMeetingAttendance(
        @Path("groupId") groupId: String,
        @Path("meetingId") meetingId: String
    ): Response<ApiEnvelope<List<Attendance>>>

    @GET("groups/{id}/ledger")
    suspend fun getGroupLedger(@Path("id") groupId: String): Response<ApiEnvelope<List<LedgerEntry>>>

    @GET("groups/{id}/funds")
    suspend fun getGroupFunds(@Path("id") groupId: String): Response<ApiEnvelope<List<FundAccount>>>

    @GET("groups/{id}/votes")
    suspend fun getGroupVotes(@Path("id") groupId: String): Response<ApiEnvelope<List<Vote>>>

    // Analytics
    @GET("analytics/portfolio")
    suspend fun getPortfolioSummary(): Response<ApiEnvelope<PortfolioSummary>>

    // Notifications
    @GET("notifications")
    suspend fun getNotifications(): Response<ApiEnvelope<List<Notification>>>

    @PATCH("notifications/{id}/read")
    suspend fun markNotificationRead(@Path("id") id: String): Response<ApiEnvelope<Notification>>
}
