Public Class frmbillrequest
    Dim billnum As String
    Dim status As String
    Dim type As String
    Dim myid As String

    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmbillrequest_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
        End If
    End Sub

    Private Sub frmbillrequest_KeyPress(sender As Object, e As KeyPressEventArgs) Handles Me.KeyPress

    End Sub

    Private Sub frmbillrequest_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        lblbillnumber.Text = frmBilling.txtBillno.Text
        viewuserbillrequest()
        Me.KeyPreview = True
    End Sub

    Private Sub SimpleButton2_Click(sender As Object, e As EventArgs) Handles SimpleButton2.Click
        Dim exists As Boolean = False
        For Each itm As DataGridViewRow In DataGridView1.Rows
            If (itm.Cells(1).Value = lblbillnumber.Text) And (itm.Cells(5).Value = "Pending") Then
                exists = True
            End If
        Next

        If txtReason.Text = "" Or cbtype.Text = "" Or lblbillnumber.Text = "" Then
            MsgBox("Please fill up all fields.", vbCritical, "Message")
        ElseIf exists = True Then
            MsgBox("Bill #: " + lblbillnumber.Text + " is already waiting for action.", vbExclamation, "Message")
        Else
            Dim result As Integer = MessageBox.Show("Are you sure you want to Request for  " + cbtype.Text + " with Bill #: " + lblbillnumber.Text + "?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                userbillrequest()
                viewuserbillrequest()
            End If
        End If
    End Sub

    Private Sub DataGridView1_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles DataGridView1.CellContentClick

    End Sub

    Private Sub DataGridView1_SelectionChanged(sender As Object, e As EventArgs) Handles DataGridView1.SelectionChanged
        On Error Resume Next
        Dim myindex As Integer = DataGridView1.CurrentRow.Index
        myid = DataGridView1.Item(0, myindex).Value
        billnum = DataGridView1.Item(1, myindex).Value
        type = DataGridView1.Item(2, myindex).Value
        status = DataGridView1.Item(5, myindex).Value
    End Sub

    Private Sub SimpleButton1_Click(sender As Object, e As EventArgs) Handles SimpleButton1.Click
        If status = "" Then
            MsgBox("Please Select Request to delete.", vbCritical, "Information")
        ElseIf status = "Pending" Then
            Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Request?", "Caution!", MessageBoxButtons.YesNo)
            If result = DialogResult.Yes Then
                deleterequestbill(myid)
                viewuserbillrequest()
            End If
        Else
            MsgBox("Unable to Delete This request. Already processed.", vbCritical, "Information")
        End If
    End Sub
End Class