Public Class frmReqDate 
    Dim mydate As String
    Dim reqby As String
    Dim status As String
    Dim myid As String
    Dim reason As String
    Private Sub frmReqDate_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        viewbackdate()
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        viewbackdate()
    End Sub

    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles SimpleButton2.Click
        If mydate = "" Then
            MsgBox("Select request to process.")
        ElseIf status = "Approved" Or status = "Denied" Then
            MsgBox("This request is already processed.")
        Else
            Dim result As Integer = MessageBox.Show(reqby + " is requesting to Back Date to " + mydate + " because of the following reasons: " + reason, "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                approveddate(myid)
                viewbackdate()
            End If
        End If
    End Sub

    Private Sub DataGridView1_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles DataGridView1.CellContentClick

    End Sub

    Private Sub DataGridView1_CellMouseClick(sender As Object, e As DataGridViewCellMouseEventArgs) Handles DataGridView1.CellMouseClick
        On Error Resume Next
        Dim myindex As Integer = DataGridView1.CurrentRow.Index
        myid = DataGridView1.Item(0, myindex).Value
        mydate = DataGridView1.Item(1, myindex).Value
        status = DataGridView1.Item(5, myindex).Value
        reqby = DataGridView1.Item(2, myindex).Value
        reason = DataGridView1.Item(4, myindex).Value
    End Sub

    Private Sub SimpleButton3_Click(sender As Object, e As EventArgs) Handles SimpleButton3.Click
        If mydate = "" Then
            MsgBox("Select request to process.")
        ElseIf status = "Approved" Or status = "Denied" Then
            MsgBox("This request is already processed.")
        Else
            Dim result As Integer = MessageBox.Show(reqby + " is requesting to Back Date to " + mydate + " because of the following reasons: " + reason + ". Are you sure you want to Disapprove this Request?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                denieddate(myid)
                viewbackdate()
            End If
        End If
    End Sub
End Class