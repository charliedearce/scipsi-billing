<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class repBill
    Inherits DevExpress.XtraEditors.XtraForm

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing AndAlso components IsNot Nothing Then
            components.Dispose()
        End If
        MyBase.Dispose(disposing)
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.XtraTabControl1 = New DevExpress.XtraTab.XtraTabControl()
        Me.XtraTabPage1 = New DevExpress.XtraTab.XtraTabPage()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.cbPeriod = New System.Windows.Forms.ComboBox()
        Me.XtraTabPage2 = New DevExpress.XtraTab.XtraTabPage()
        Me.SimpleButton1 = New DevExpress.XtraEditors.SimpleButton()
        Me.txtcusper = New System.Windows.Forms.TextBox()
        Me.XtraTabPage3 = New DevExpress.XtraTab.XtraTabPage()
        Me.LabelControl1 = New DevExpress.XtraEditors.LabelControl()
        Me.SimpleButton2 = New DevExpress.XtraEditors.SimpleButton()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.dtpBillTo = New DevExpress.XtraEditors.DateEdit()
        Me.dtpBillFrom = New DevExpress.XtraEditors.DateEdit()
        Me.SaveFileDialog1 = New System.Windows.Forms.SaveFileDialog()
        CType(Me.XtraTabControl1, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.XtraTabControl1.SuspendLayout()
        Me.XtraTabPage1.SuspendLayout()
        Me.XtraTabPage2.SuspendLayout()
        Me.XtraTabPage3.SuspendLayout()
        CType(Me.dtpBillTo.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.dtpBillTo.Properties, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.dtpBillFrom.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.dtpBillFrom.Properties, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'XtraTabControl1
        '
        Me.XtraTabControl1.Location = New System.Drawing.Point(12, 12)
        Me.XtraTabControl1.Name = "XtraTabControl1"
        Me.XtraTabControl1.SelectedTabPage = Me.XtraTabPage1
        Me.XtraTabControl1.Size = New System.Drawing.Size(326, 139)
        Me.XtraTabControl1.TabIndex = 2
        Me.XtraTabControl1.TabPages.AddRange(New DevExpress.XtraTab.XtraTabPage() {Me.XtraTabPage1, Me.XtraTabPage2, Me.XtraTabPage3})
        '
        'XtraTabPage1
        '
        Me.XtraTabPage1.Controls.Add(Me.btn1)
        Me.XtraTabPage1.Controls.Add(Me.cbPeriod)
        Me.XtraTabPage1.Name = "XtraTabPage1"
        Me.XtraTabPage1.Size = New System.Drawing.Size(320, 108)
        Me.XtraTabPage1.Text = "SELECT"
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(95, 65)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 9
        Me.btn1.Text = "EXPORT"
        '
        'cbPeriod
        '
        Me.cbPeriod.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbPeriod.FlatStyle = System.Windows.Forms.FlatStyle.System
        Me.cbPeriod.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbPeriod.FormattingEnabled = True
        Me.cbPeriod.Location = New System.Drawing.Point(50, 21)
        Me.cbPeriod.Margin = New System.Windows.Forms.Padding(4)
        Me.cbPeriod.Name = "cbPeriod"
        Me.cbPeriod.Size = New System.Drawing.Size(221, 36)
        Me.cbPeriod.TabIndex = 8
        '
        'XtraTabPage2
        '
        Me.XtraTabPage2.Controls.Add(Me.SimpleButton1)
        Me.XtraTabPage2.Controls.Add(Me.txtcusper)
        Me.XtraTabPage2.Name = "XtraTabPage2"
        Me.XtraTabPage2.Size = New System.Drawing.Size(320, 108)
        Me.XtraTabPage2.Text = "CUSTOM"
        '
        'SimpleButton1
        '
        Me.SimpleButton1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton1.Appearance.Options.UseFont = True
        Me.SimpleButton1.Location = New System.Drawing.Point(95, 65)
        Me.SimpleButton1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.SimpleButton1.Name = "SimpleButton1"
        Me.SimpleButton1.Size = New System.Drawing.Size(131, 39)
        Me.SimpleButton1.TabIndex = 10
        Me.SimpleButton1.Text = "EXPORT"
        '
        'txtcusper
        '
        Me.txtcusper.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtcusper.Location = New System.Drawing.Point(52, 20)
        Me.txtcusper.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.txtcusper.MaxLength = 50
        Me.txtcusper.Name = "txtcusper"
        Me.txtcusper.Size = New System.Drawing.Size(217, 34)
        Me.txtcusper.TabIndex = 2
        '
        'XtraTabPage3
        '
        Me.XtraTabPage3.Controls.Add(Me.LabelControl1)
        Me.XtraTabPage3.Controls.Add(Me.SimpleButton2)
        Me.XtraTabPage3.Controls.Add(Me.Label2)
        Me.XtraTabPage3.Controls.Add(Me.Label1)
        Me.XtraTabPage3.Controls.Add(Me.dtpBillTo)
        Me.XtraTabPage3.Controls.Add(Me.dtpBillFrom)
        Me.XtraTabPage3.Name = "XtraTabPage3"
        Me.XtraTabPage3.Size = New System.Drawing.Size(320, 108)
        Me.XtraTabPage3.Text = "DATE RANGE"
        '
        'LabelControl1
        '
        Me.LabelControl1.Appearance.Font = New System.Drawing.Font("Tahoma", 10.2!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.LabelControl1.Location = New System.Drawing.Point(149, 21)
        Me.LabelControl1.Name = "LabelControl1"
        Me.LabelControl1.Size = New System.Drawing.Size(23, 21)
        Me.LabelControl1.TabIndex = 12
        Me.LabelControl1.Text = "__"
        '
        'SimpleButton2
        '
        Me.SimpleButton2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.SimpleButton2.Appearance.Options.UseFont = True
        Me.SimpleButton2.Location = New System.Drawing.Point(95, 65)
        Me.SimpleButton2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.SimpleButton2.Name = "SimpleButton2"
        Me.SimpleButton2.Size = New System.Drawing.Size(131, 39)
        Me.SimpleButton2.TabIndex = 11
        Me.SimpleButton2.Text = "EXPORT"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Location = New System.Drawing.Point(183, 4)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(31, 17)
        Me.Label2.TabIndex = 3
        Me.Label2.Text = "TO:"
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Location = New System.Drawing.Point(3, 4)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(49, 17)
        Me.Label1.TabIndex = 2
        Me.Label1.Text = "FROM:"
        '
        'dtpBillTo
        '
        Me.dtpBillTo.EditValue = New Date(2015, 6, 30, 14, 33, 33, 575)
        Me.dtpBillTo.Location = New System.Drawing.Point(186, 24)
        Me.dtpBillTo.Name = "dtpBillTo"
        Me.dtpBillTo.Properties.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dtpBillTo.Properties.Appearance.Options.UseFont = True
        Me.dtpBillTo.Properties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.dtpBillTo.Properties.CalendarTimeProperties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.dtpBillTo.Size = New System.Drawing.Size(131, 34)
        Me.dtpBillTo.TabIndex = 1
        '
        'dtpBillFrom
        '
        Me.dtpBillFrom.EditValue = New Date(2015, 6, 30, 0, 0, 0, 0)
        Me.dtpBillFrom.Location = New System.Drawing.Point(3, 24)
        Me.dtpBillFrom.Name = "dtpBillFrom"
        Me.dtpBillFrom.Properties.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.dtpBillFrom.Properties.Appearance.Options.UseFont = True
        Me.dtpBillFrom.Properties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.dtpBillFrom.Properties.CalendarTimeProperties.Buttons.AddRange(New DevExpress.XtraEditors.Controls.EditorButton() {New DevExpress.XtraEditors.Controls.EditorButton(DevExpress.XtraEditors.Controls.ButtonPredefines.Combo)})
        Me.dtpBillFrom.Size = New System.Drawing.Size(131, 34)
        Me.dtpBillFrom.TabIndex = 0
        '
        'repBill
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(350, 155)
        Me.Controls.Add(Me.XtraTabControl1)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.FormBorderStyle = System.Windows.Forms.FormBorderStyle.FixedToolWindow
        Me.MaximizeBox = False
        Me.MinimizeBox = False
        Me.Name = "repBill"
        Me.ShowInTaskbar = False
        Me.StartPosition = System.Windows.Forms.FormStartPosition.CenterParent
        Me.Text = "BILL REPORT FOR SUN"
        CType(Me.XtraTabControl1, System.ComponentModel.ISupportInitialize).EndInit()
        Me.XtraTabControl1.ResumeLayout(False)
        Me.XtraTabPage1.ResumeLayout(False)
        Me.XtraTabPage2.ResumeLayout(False)
        Me.XtraTabPage2.PerformLayout()
        Me.XtraTabPage3.ResumeLayout(False)
        Me.XtraTabPage3.PerformLayout()
        CType(Me.dtpBillTo.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.dtpBillTo.Properties, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.dtpBillFrom.Properties.CalendarTimeProperties, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.dtpBillFrom.Properties, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents XtraTabControl1 As DevExpress.XtraTab.XtraTabControl
    Friend WithEvents XtraTabPage1 As DevExpress.XtraTab.XtraTabPage
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents cbPeriod As System.Windows.Forms.ComboBox
    Friend WithEvents XtraTabPage2 As DevExpress.XtraTab.XtraTabPage
    Friend WithEvents SimpleButton1 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents txtcusper As System.Windows.Forms.TextBox
    Friend WithEvents XtraTabPage3 As DevExpress.XtraTab.XtraTabPage
    Friend WithEvents dtpBillTo As DevExpress.XtraEditors.DateEdit
    Friend WithEvents dtpBillFrom As DevExpress.XtraEditors.DateEdit
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents LabelControl1 As DevExpress.XtraEditors.LabelControl
    Friend WithEvents SimpleButton2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents SaveFileDialog1 As System.Windows.Forms.SaveFileDialog
End Class
